<?php

declare (strict_types=1);

namespace App\Domain\Base\Repo\RC\Utils;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Enums\RCApiOperationType;
use App\Domain\Common\Entities\UrlPath;
use App\Infrastructure\Services\AppService;
use DDD\Domain\Base\Entities\MessageHandlers\AppMessageHandler;
use DDD\Infrastructure\Exceptions\ExceptionDetails;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Libs\Config;
use DDD\Infrastructure\Reflection\ClassWithNamespace;
use DDD\Infrastructure\Reflection\ReflectionClass;
use DDD\Infrastructure\Traits\Serializer\SerializerTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Response;
use RankingCoach\Inc\Core\Base\BaseConstants;
use ReflectionException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

class RCApiOperations
{
    use SerializerTrait;

    /** @var RCApiOperation[]|null */
    public ?array $operations;
    /** @var RCApiOperation[]|null */
    public array $operationsById;
    /**
     * @var RCApiOperation[]|null
     */
    public array $operationsGroupedByEndpoints;
    /**
     * @var RCApiOperation[]|null
     */
    public array $operationsGroupedByEndpointsFinal;
    /**
     * @var null
     */
    private $dboperationsGroupedByHashes;
    /** @var array */
    private array $dboperationsHashes;
    /** @var array Cache for executed RC calls, can be displayed for debugging purposes by getExecutedRCCalls */
    private static array $executedRCCalls = [];

    public function __construct()
    {
        $this->operations = [];
        $this->operationsGroupedByEndpoints = [];
        $this->operationsGroupedByEndpointsFinal = [];
        $this->operationsById = [];
    }

    /**
     * @param RCApiOperation $operation
     * @return void
     */
    public function addOperation(RCApiOperation &$operation)
    {
        if (isset($this->operationsById[$operation->id])) // keep sure, we are not adding duplicates
        {
            return;
        }
        $this->operations[] = $operation;
        $endpoint = $operation->endpoint;
        $id = $operation->id;
        if (!isset($this->operationsGroupedByEndpoints[$endpoint])) {
            $this->operationsGroupedByEndpoints[$endpoint] = [
                'operationsByGeneralParamsHash' => [],
                'mergelimit' => 1,
                'operations' => [],
            ];
        }
        if (!isset($this->operationsGroupedByEndpointsFinal[$endpoint])) {
            $this->operationsGroupedByEndpointsFinal[$endpoint] = [];
        }
        $this->operationsById[$id] = $operation;
        $this->operationsGroupedByEndpoints[$endpoint]['operations'][] = $operation;
        /**
         * handle merging of operations:
         * in some cases e.g. adwords data, it is possibile to split operations to every single element e.g. one adwords call per keyword,
         * but it is more efficient to merge many e.g. keywords to one adwords call
         */
        $this->operationsGroupedByEndpoints[$endpoint]['mergelimit'] = $operation->mergelimit;
        $generalParamsHash = md5(
            json_encode($operation->generalParams ?? null, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        if (!isset($this->operationsGroupedByEndpoints[$endpoint]['operationsByGeneralParamsHash'][$generalParamsHash])) {
            $this->operationsGroupedByEndpoints[$endpoint]['operationsByGeneralParamsHash'][$generalParamsHash] = ['actMergeOutstandingOperations' => []];
        }
        $this->operationsGroupedByEndpoints[$endpoint]['operationsByGeneralParamsHash'][$generalParamsHash]['actMergeOutstandingOperations'][] = $operation;
        $this->handleOutstandingMerges($endpoint);
    }

    /**
     * handle merging of operations:
     * in some cases e.g. adwords data, it is possibile to split operations to every single element e.g. one adwords call per keyword,
     * but it is more efficient to merge many e.g. keywords to one adwords call
     * ###
     * Looks after outstanding operations, that have to be merged to one single endpoint call
     * @param $endpoint
     * @param bool $force if true, merges always
     */
    private function handleOutstandingMerges(string $endpoint, bool $force = false): void
    {
        $endpointGroup = $this->operationsGroupedByEndpoints[$endpoint];
        foreach ($endpointGroup['operationsByGeneralParamsHash'] as $hash => $generalParamsHashgroup) {
            if (
                count($generalParamsHashgroup['actMergeOutstandingOperations']) && (count(
                        $generalParamsHashgroup['actMergeOutstandingOperations']
                    ) % $endpointGroup['mergelimit'] == 0 || $force)
            ) {//the last group is full, we have to create a new endpoint call
                $id = '';
                $mergedParams = [];
                if ($generalParamsHashgroup['actMergeOutstandingOperations'][0]->generalParams ?? false) {
                    $mergedParams = array_merge_recursive(
                        $mergedParams,
                        $generalParamsHashgroup['actMergeOutstandingOperations'][0]->generalParams
                    );
                }
                foreach ($generalParamsHashgroup['actMergeOutstandingOperations'] as $outstandingOperation) {
                    $id .= ($id ? '_' : '') . $outstandingOperation->id; // we concatenate the ids of every single operation to obtain the merged groups id
                    $mergedParams = array_merge_recursive(
                        $mergedParams,
                        $outstandingOperation->params
                    ); //we merge the parameters e.g. keywords
                }
                //general params are not merged recoursive since they are duplicated otherwise
                $mergedParams['id'] = md5($id);
                $this->operationsById[$mergedParams['id']] = $generalParamsHashgroup['actMergeOutstandingOperations'];
                $generalParamsHashgroup['actMergeOutstandingOperations'] = []; //we reset the outstanding operations array
                $this->operationsGroupedByEndpointsFinal[$endpoint][] = $mergedParams;
                // we write the settings back, since we operated on array copy and these changes are not applied
                $endpointGroup['operationsByGeneralParamsHash'][$hash] = $generalParamsHashgroup;
                $this->operationsGroupedByEndpoints[$endpoint] = $endpointGroup;
            }
        }
    }

    /**
     * @param bool $displayCall
     * @param bool $displayResponse
     * @param bool $useApiACallCache
     * @param bool $logOperationCalls
     * @param string $operationType
     * @return void
     * @throws ReflectionException
     */
    public function execute(
        bool               $displayCall = false,
        bool               $displayResponse = false,
        bool               $useApiACallCache = true,
        bool               $logOperationCalls = true,
        string             $operationType = RCApiOperationType::LOAD,
        float              $timeout = 0
    ): void {
        // first we have to resolve all the outanding merges, that are not combined into one endpoint call:
        // e.g. merge_limit 2 and 3 operations added => the last operation is still not in the endpoints_final
        foreach ($this->operationsGroupedByEndpoints as $endpoint => $ops) {
            $this->handleOutstandingMerges($endpoint, true);
        }
        //we need to split the calls, if they are too big:
        // first we count the calls
        $totalOperations = 0;

        if ($displayCall) {
            echo wp_json_encode($this->operationsGroupedByEndpointsFinal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }
        foreach ($this->operationsGroupedByEndpointsFinal as $endpoint) {
            foreach ($endpoint as $call) {
                $totalOperations++;
            }
        }
        $numApiCalls = ceil($totalOperations / 300); //max 200 operations per call
        for ($i = 0; $i < $numApiCalls; $i++) {
            $actCall = null;
            if ($numApiCalls > 1) {
                $actCall = [];
                foreach ($this->operationsGroupedByEndpointsFinal as $endPoint => $callData) {
                    foreach ($callData as $index => $call) {
                        if ($index % $numApiCalls == $i) {
                            if (!isset($actCall[$endPoint])) {
                                $actCall[$endPoint] = [];
                            }
                            $actCall[$endPoint][] = $call;
                        }
                    }
                }
            } else {
                $actCall = $this->operationsGroupedByEndpointsFinal;
            }

            if (!$actCall || !count($actCall)) {//nothing to load
                return;
            }

            $results = $this->callRCApi($actCall, $useApiACallCache, $logOperationCalls, $timeout);

            if ($displayResponse) {
                echo wp_json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                return;
            }

            if (!empty($results)) {
                foreach ($results as $endpoint => $responses) {
                    foreach ($responses as $id => $response) {
                        if (isset($this->operationsById[$id])) {
                            if (is_array($this->operationsById[$id])) {
                                foreach ($this->operationsById[$id] as $op) {
                                    /** @var RCApiOperation $op */
                                    $op->handleResponse($response, $operationType);
                                }
                            } elseif (
                                is_object($this->operationsById[$id]) && method_exists(
                                    $this->operationsById[$id],
                                    'handleResponse'
                                )
                            ) {
                                $this->operationsById[$id]->handleResponse($response, $operationType);
                            }
                        }
                    }
                }
            }
            unset($actCall);
        }
    }

    /**
     * Returns call stack as string with ClassName->method:line|ClassName->method:line ... ignoring some classes such as RCApiOperations
     * In case of message handlers returns MessageHandler:transport_name
     * In case of CLI commands returns CLI:command_name
     * For logging purposes
     * @param int $maxDept
     * @return string
     */
    public static function getCallStackAsString(int $maxDept = 7): string
    {
        $routeAndTrace = '';
        try {
            $requestService = AppService::instance()->getRequestService();
            $request = $requestService->getRequestStack()->getMainRequest();
            $route = '';
            if ($request) {
                $route = $request->attributes->get('_route', 'N/A');
                $parameters = $request->attributes->get('_route_params', []);
            }
            // Capture debug backtrace
            $callStackAsString = '';
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
            $debugBacktrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $classesToIgnore = [
                'RCApiOperations' => true,
                'HttpKernelRunner' => true,
                'Kernel' => true,
                'HttpKernel' => true,
                'Application' => true,
                'Command' => true,
                'ConsoleApplicationRunner' => true,
                'RejectRedeliveredMessageMiddleware' => true,
                'DispatchAfterCurrentBusMiddleware' => true,
                'FailedMessageProcessingMiddleware' => true,
                'SendMessageMiddleware' => true,
                'HandleMessageMiddleware' => true,
                'SyncTransport' => true,
                'RoutableMessageBus' => true,
                'TraceableMessageBus' => true,
                'MessageBus' => true,
                'TraceableMiddleware' => true,
                'AddBusNameStampMiddleware' => true,
            ];
            $currentLevel = 0;
            // we treat CLI commands and message handlers as entry points
            $cliCommand = null;
            $messageHandler = null;
            foreach ($debugBacktrace as $traceElement) {
                if (!isset($traceElement['class'])) {
                    continue;
                }
                $classWithNamespace = new ClassWithNamespace($traceElement['class']);
                if (isset($classesToIgnore[$classWithNamespace->name])) {
                    continue;
                }
                if (is_a($traceElement['class'], AppMessageHandler::class, true)) {
                    $messageHandlerReflectionClass = ReflectionClass::instance((string)$traceElement['class']);
                    /** @var AsMessageHandler $asMessageHandlerAttribute */
                    $asMessageHandlerAttribute = $messageHandlerReflectionClass->getAttributeInstance(
                        AsMessageHandler::class
                    );
                    if ($asMessageHandlerAttribute && $asMessageHandlerAttribute->fromTransport) {
                        $messageHandler = $asMessageHandlerAttribute->fromTransport;
                    }
                } elseif (is_a($traceElement['class'], Command::class, true)) {
                    $commandReflectionClass = ReflectionClass::instance((string)$traceElement['class']);
                    /** @var AsCommand $asCommandAttribute */
                    $asCommandAttribute = $commandReflectionClass->getAttributeInstance(AsCommand::class);
                    if ($asCommandAttribute && $asCommandAttribute->name) {
                        $cliCommand = $asCommandAttribute->name;
                    }
                }
                if ($currentLevel <= $maxDept) {
                    $callStackAsString = $classWithNamespace->name . '->' . $traceElement['function'] . ':' . $traceElement['line'] . ($callStackAsString ? '|' : '') . $callStackAsString;
                }
                $currentLevel++;
            }
            if ($messageHandler) {
                $routeAndTrace = 'MessageHandler:' . $messageHandler . '::' . $callStackAsString;
            } elseif ($cliCommand) {
                $routeAndTrace = 'CLI:' . $cliCommand . '::' . $callStackAsString;
            } else {
                $routeAndTrace = $route . '::' . $callStackAsString;
            }
        } catch (Throwable $t) {
            return $routeAndTrace;
        }
        return $routeAndTrace;
    }

    /**
     * calls rc api with call data and executes calls to microservices as single calls and monolith calls in one
     * all calls are executed in parallel and return in a standardized format summarized by endpoints and call id
     * @param array $callData
     * @param bool $useCache
     * @param bool $logCall
     * @return array|object
     */
    private function callRCApi(
        array &$callData,
        bool $useCache = false,
        bool $logCall = true,
        float $timeout = 0
    ): array|object {
        $callRCRequestConfig = Config::get('Base.Repo.rc.requestSettings');
        $httpClient = new Client();
        $monolithCalls = [];
        $callPromises = [];
        $monolithConfig = Config::get('Base.Repo.rc.monolith');
        $microserviceBaseUrl = Config::getEnv('RC_API_URL') ?: Config::get('Base.Repo.rc.microservices.baseUrl');
        //used to put endpoint and id into a single array index divided by delimiter
        $endpointToIdDelimiter = '___##___';
        // we handle old monolith calls and microservice calls separately.
        // monolith calls are done in a single call, ms calls are done each separately.
        $demandToRetrieveTheRawBodyInPureHtmlFormat = false;


        //We add account and Route/Command/MessageHandler alongside with the most recent call stack entries to Logging Data
        $accountId = get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, null);
        $routeCommandOrMessageHandlerAndTraceAsString = self::getCallStackAsString();

        foreach ($callData as $endpoint => $parameters) {
            // microservice calls have request method as prefix, by this we determine if we have a microsercice call or an
            // monolith call
            preg_match('/^(?P<method>(GET|PUT|POST|DELETE|PATCH)):(?P<url>[\S]+)/', $endpoint, $matches);
            if (isset($matches['url']) && isset($matches['method'])) {
                $urlPath = new UrlPath($matches['url']);
                $callparams = $parameters[0];
                // calldata is organized by endpoints and call data
                // each element contains [params => [... params for the endpoint call ...], id => the id of the call]
                foreach ($parameters as $parametersAndCallId) {
                    if (isset($parametersAndCallId['returnRawHtml'])) {
                        $demandToRetrieveTheRawBodyInPureHtmlFormat = (bool)$parametersAndCallId['returnRawHtml'];
                    }
                    $parametersAndCallId['http_errors'] = false;
                    // we put the hhtp request into the call promises under an index combined of endpoint and id of the call
                    $callUrl = (strpos(
                            $urlPath->path,
                            'http'
                        ) === false) ? $microserviceBaseUrl . $urlPath : $urlPath->path;
                    unset($parametersAndCallId['merge']);
                    unset($parametersAndCallId['mergelimit']);
                    unset($parametersAndCallId['mergeindices']);
                    if (isset($parametersAndCallId['body'])) {
                        if (is_object($parametersAndCallId['body']) || is_array($parametersAndCallId['body'])) {
                            $parametersAndCallId['body'] = json_encode(
                                $parametersAndCallId['body'],
                                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                            );
                        }
                    }
                    $parametersAndCallId['timeout'] = $timeout;
                    $parametersAndCallId['headers'] =
                        (isset($parametersAndCallId['headers']) && is_array($parametersAndCallId['headers']))
                            ? array_merge($parametersAndCallId['headers'], $callRCRequestConfig['headers'])
                            : $callRCRequestConfig['headers'];
                    $parametersAndCallId['headers']['rc-tracking'] = 'accountId=' . ($accountId??'') . ' calledFrom=' . $routeCommandOrMessageHandlerAndTraceAsString;
                    $callPromises[$endpoint . $endpointToIdDelimiter . $parametersAndCallId['id']] = $httpClient->requestAsync(
                        $matches['method'],
                        $callUrl,
                        $parametersAndCallId
                    );
                    if (RCLoad::$logRCCalls) {
                        self::$executedRCCalls[$endpoint . $endpointToIdDelimiter . $parametersAndCallId['id']] = ['call' => $parameters];
                    }
                }
            } else {
                $monolithCalls[$endpoint] = $parameters;
            }
        }
        // handle monolith call
        if ($monolithCalls) {
            // transform data into a format that the monolith expects
            $monolithInput = [
                'data' => $monolithCalls,
                'auth' => [
                    'user' => Config::getEnv('API_ARGUS_MONOLITH_USER'),
                    'password' => Config::getEnv('API_ARGUS_MONOLITH_PASSWORD')
                ]
            ];
            $monolithCallData = [
                'input' => base64_encode(json_encode($monolithInput, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                'settings' => json_encode(['returnJson' => false, 'serialize' => false],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ];
            $callPromises['monolith'] = $httpClient->postAsync(
                $monolithConfig['baseUrl'],
                ['form_params' => $monolithCallData, 'http_errors' => false]
            );
            if (RCLoad::$logRCCalls) {
                self::$executedRCCalls['monolith'] = ['call' => $monolithCalls];
            }
        }
        /** @var $responses Response[] */
        $responses = Promise\Utils::unwrap($callPromises);
        $results = [];

        foreach ($responses as $endpointAndId => $response) {
            // all microservice response are structured as Endpoint___Delimiter___Id
            if ($endpointAndId !== 'monolith') {
                // explode endpoints and ids by delimiter
                $endpointAndIdExploded = explode($endpointToIdDelimiter, $endpointAndId);
                $endpoint = $endpointAndIdExploded[0];
                $callId = $endpointAndIdExploded[1];
                if (!isset($results[$endpoint])) {
                    $results[$endpoint] = [];
                }
                //echo $response->getBody(); die;
                $responseBody = (string)$response->getBody();

                $results[$endpoint][$callId] = json_decode($responseBody);
                if($demandToRetrieveTheRawBodyInPureHtmlFormat) {
                    $results[$endpoint][$callId] = $responseBody;
                    if (RCLoad::$logRCCalls) {
                        self::$executedRCCalls[$endpointAndId]['response'] = $results[$endpoint][$callId];
                    }
                }
                else {
                    // First-first-first we save the response body
                    if (RCLoad::$logRCCalls) {
                        self::$executedRCCalls[$endpointAndId]['response'] = $results[$endpoint][$callId];
                    }
                    // throw InternalErrorException if the response is not a valid json
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        if (RCLoad::$logRCCalls) {
                            self::$executedRCCalls[$endpointAndId]['response'] = $responseBody;
                        }
                        $exceptionDetails = new ExceptionDetails();
                        $exceptionDetails->addDetail($responseBody, debug_backtrace());
                        throw new InternalErrorException('Invalid API', $exceptionDetails);
                    }
                    // throw InternalErrorException if the response contains an error
                    if (isset($results[$endpoint][$callId]->error)) {
                        $exceptionDetails = new ExceptionDetails();
                        $exceptionDetails->addDetail($responseBody, debug_backtrace());
                        throw new InternalErrorException('Error API', $exceptionDetails);
                    }
                }
            } else {
                $monolithReponse = json_decode((string)$response->getBody());
                // monolith calls are summarized into one call on the monolith endpoint and the $resonse itself is organized
                // as associative array of Endpoint___Delimiter___Id => $result so we need to decompose it
                foreach ($monolithReponse as $endpoint => $resultsGroupedById) {
                    // we do not need to check if the endpoint index already exists, as monolith calls have different endpoints
                    // and are already grouped by these names
                    if ($endpoint !== 'errors') { // we do not need the errors index anymore
                        $results[$endpoint] = $resultsGroupedById;
                    }
                }
                if (RCLoad::$logRCCalls) {
                    self::$executedRCCalls['monolith'] = $monolithReponse;
                }
            }
        }
        return $results;
    }

    public static function getExecutedRCCalls(): array
    {
        return self::$executedRCCalls;
    }
}
