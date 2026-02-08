<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\FeatureFlagsConfiguration;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

/**
 * Class FeatureFlagManager
 *
 * A centralized service for managing feature flags across the SEO operations.
 * This allows enabling/disabling features from a single location without
 * modifying individual operation classes.
 */
class FeatureFlagManager
{
    use RcLoggerTrait;

    /**
     * @var array Global feature flags that apply to all operations
     */
    private array $globalFlags = [];

    /**
     * @var array Operation-specific feature flags
     */
    private array $operationFlags = [];

    /**
     * @var array Mapping of operation keys to their factor names
     */
    public array $operationFactorMap = [];

    /**
     * @var array Mapping of factor names to their context names
     */
    public array $factorContextMap = [];

    /**
     * @var array Mapping of operation keys to their context names (derived from factor-context mapping)
     */
    public array $operationContextMap = [];

    /**
     * @var ?FeatureFlagManager Singleton instance
     */
    private static ?FeatureFlagManager $instance = null;

    /**
     * @var CapabilityManager The capability manager instance
     */
    private CapabilityManager $capabilityManager;

    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct()
    {
        $this->capabilityManager = CapabilityManager::instance();
        $this->buildOperationFactorMap();
        $this->initializeDefaultFlags();
    }

    /**
     * Get the singleton instance
     *
     * @return FeatureFlagManager
     */
    public static function getInstance(): FeatureFlagManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize default feature flags
     */
    private function initializeDefaultFlags(): void
    {
        // Set default global flags
        $this->globalFlags = [
            'external_api_call' => true,
        ];

        // Set default operation-specific flags
        $this->operationFlags = [];

        // Load configuration from file if it exists
        $this->loadConfiguration();
    }

    /**
     * Build a mapping of operation keys to their factor names and contexts
     * Uses caching to avoid expensive directory scanning on every request
     */
    private function buildOperationFactorMap(): void
    {
        // Check if we have a cached mapping in the transient API
        $cachedMaps = get_transient(BaseConstants::OPTION_FEATURE_FLAG_OPERATION_FACTOR_CONTEXT_MAP);

        if ($cachedMaps !== false) {
            $this->operationFactorMap = $cachedMaps['operationFactorMap'] ?? [];
            $this->factorContextMap = $cachedMaps['factorContextMap'] ?? [];
            $this->operationContextMap = $cachedMaps['operationContextMap'] ?? [];
            return;
        }

        // Path to the Operations directory
        $operationsPath = RANKINGCOACH_PLUGIN_APP_DIR . 'src/Domain/Integrations/WordPress/Seo/Entities/Optimiser/Operations';
        if (!is_dir($operationsPath)) {
            $this->log('Operations directory not found: ' . $operationsPath, 'WARNING');
            return;
        }
        $this->scanOperationsDirectory($operationsPath);

        // Path to the Factors directory
        $factorsPath = RANKINGCOACH_PLUGIN_APP_DIR . 'src/Domain/Integrations/WordPress/Seo/Entities/Optimiser/Factors';
        if (!is_dir($factorsPath)) {
            $this->log('Factors directory not found: ' . $factorsPath, 'WARNING');
            return;
        }
        $this->scanFactorsDirectory($factorsPath);

        // Build the operation-context mapping based on the factor-context mapping
        $this->buildOperationContextMap();

        // Cache all mappings for future requests
        $maps = [
            'operationFactorMap' => $this->operationFactorMap,
            'factorContextMap' => $this->factorContextMap,
            'operationContextMap' => $this->operationContextMap
        ];

        set_transient(BaseConstants::OPTION_FEATURE_FLAG_OPERATION_FACTOR_CONTEXT_MAP, $maps, HOUR_IN_SECONDS);
    }

    /**
     * Build a mapping of operation keys to their context names
     * This is derived from the operation-factor and factor-context mappings
     */
    private function buildOperationContextMap(): void
    {
        foreach ($this->operationFactorMap as $operationKey => $factorName) {
            $this->operationContextMap[$operationKey] = $this->factorContextMap[$factorName] ?? null;
        }
    }

    /**
     * Recursively scan the Operations directory to build the operation-factor map
     * This is only called when the cache is empty or expired
     * 
     * @param string $dir The directory to scan
     * @param string|null $currentFactor The current factor being processed
     */
    private function scanOperationsDirectory(string $dir, ?string $currentFactor = null): void
    {
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                // This is a factor directory
                $factorName = $item;
                $this->scanOperationsDirectory($path, $factorName);
            } elseif (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                // This is a PHP file, check if it's an operation class
                if (str_contains($item, 'Operation.php') && $currentFactor !== null) {
                    // Extract the class name without the .php extension
                    $className = pathinfo($item, PATHINFO_FILENAME);

                    // Convert the class name to an operation key (snake_case)
                    $operationKey = $this->pascalToSnake($className);

                    // Convert the class name to an operation key (snake_case)
                    $factorKey = $this->pascalToSnake($currentFactor);

                    // Map the operation key to its factor
                    $this->operationFactorMap[$operationKey] = $factorKey . '_factor';
                }
            }
        }
    }

    /**
     * Recursively scan the Factors directory to build the factor-context map
     * This is only called when the cache is empty or expired
     *
     * @param string $dir The directory to scan
     * @param string|null $currentContext The current context being processed
     */
    private function scanFactorsDirectory(string $dir, ?string $currentContext = null): void
    {
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                // This is a factor directory
                $contextName = $item;
                $this->scanFactorsDirectory($path, $contextName);
            } elseif (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                // This is a PHP file, check if it's an operation class
                if (str_contains($item, 'Factor.php') && $currentContext !== null) {
                    // Extract the class name without the .php extension
                    $className = pathinfo($item, PATHINFO_FILENAME);

                    // Convert the class name to an operation key (snake_case)
                    $factorKey = $this->pascalToSnake($className);

                    // Convert the class name to an operation key (snake_case)
                    $contextKey = $this->pascalToSnake($currentContext);

                    // Map the operation key to its factor
                    $this->factorContextMap[$factorKey] = $contextKey . '_context';
                }
            }
        }
    }

    /**
     * Convert PascalCase to snake_case
     * 
     * @param string $input The PascalCase string
     * @return string The snake_case string
     */
    private function pascalToSnake(string $input): string
    {
        $pattern = '/(?<!^)[A-Z]/';
        $replacement = '_$0';
        return strtolower(preg_replace($pattern, $replacement, $input));
    }

    /**
     * Load feature flag configuration from file
     */
    private function loadConfiguration(): void
    {
        // Path to app configuration file
        $featureFlagsConfigInstance = new FeatureFlagsConfiguration();
        $config = $featureFlagsConfigInstance->get();

        // Load global flags
        if (isset($config['global']) && is_array($config['global'])) {
            $this->globalFlags = array_merge($this->globalFlags, $config['global']);
        }

        // Load operation-specific flags
        if (isset($config['operations']) && is_array($config['operations'])) {
            foreach ($config['operations'] as $operationKey => $flags) {
                // Skip if not an array
                if (!is_array($flags)) {
                    continue;
                }

                // Check if the operation has a factor mapping
                $factorName = $this->operationFactorMap[$operationKey] ?? null;

                // Case 1: Operation has no factor mapping - load flags unconditionally
                // Case 2: Operation has a factor mapping AND user has the capability - load flags
                // Case 3: Operation has a factor mapping BUT user lacks the capability - skip loading flags and log
                if ($factorName === null) {
                    // Case 1: No factor mapping, load flags unconditionally
                    if (!isset($this->operationFlags[$operationKey])) {
                        $this->operationFlags[$operationKey] = [];
                    }
                    $this->operationFlags[$operationKey] = array_merge($this->operationFlags[$operationKey], $flags);
                } elseif ($this->capabilityManager->hasFactorCapability($factorName)) {
                    // Case 2: User has the capability for this factor, load flags
                    if (!isset($this->operationFlags[$operationKey])) {
                        $this->operationFlags[$operationKey] = [];
                    }
                    $this->operationFlags[$operationKey] = array_merge($this->operationFlags[$operationKey], $flags);
                } else {
                    // Case 3: User lacks the capability for this factor, skip loading flags and log
                    $this->log("Operation '$operationKey' flags not loaded due to missing capability for factor '$factorName'", 'WARNING');
                }
            }
        }
    }

    /**
     * Set a global feature flag
     *
     * @param string $flag The feature flag name
     * @param bool $value The feature flag value
     * @return void
     */
    public function setGlobalFlag(string $flag, bool $value): void
    {
        $this->globalFlags[$flag] = $value;
        //$this->log("Global feature flag '$flag' set to " . ($value ? 'true' : 'false'));
    }

    /**
     * Set an operation-specific feature flag
     *
     * @param string $operationKey The operation key
     * @param string $flag The feature flag name
     * @param bool $value The feature flag value
     * @return void
     */
    public function setOperationFlag(string $operationKey, string $flag, bool $value): void
    {
        if (!isset($this->operationFlags[$operationKey])) {
            $this->operationFlags[$operationKey] = [];
        }

        $this->operationFlags[$operationKey][$flag] = $value;
        //$this->log("Feature-flag '$flag' for operation '$operationKey' set to " . ($value ? 'true' : 'false'));
    }

    /**
     * Get a feature flag value
     *
     * @param string $flag The feature flag name
     * @param string|null $operationKey The operation key (optional)
     * @return bool The feature flag value
     */
    public function getFlag(string $flag, ?string $operationKey = null): bool
    {
        // If an operation key is provided, check if the user has the capability for its factor
        if ($operationKey !== null) {
            $factorName = $this->operationFactorMap[$operationKey] ?? null;

            // If we have a factor mapping and the user doesn't have the capability, return false
            if ($factorName !== null && !$this->capabilityManager->hasFactorCapability($factorName)) {
                $this->log("Flag '$flag' for operation '$operationKey' is disabled due to missing capability for factor '$factorName'", 'WARNING');
                return false;
            }

            // Check operation-specific flag
            if (isset($this->operationFlags[$operationKey][$flag])) {
                return $this->operationFlags[$operationKey][$flag];
            }
        }

        // Fall back to global flag
        return $this->globalFlags[$flag] ?? true;
    }

    /**
     * Check if a feature flag is enabled
     *
     * @param string $flag The feature flag name
     * @param string|null $operationKey The operation key (optional)
     * @return bool True if the feature flag is enabled
     */
    public function isEnabled(string $flag, ?string $operationKey = null): bool
    {
        return $this->getFlag($flag, $operationKey);
    }

    /**
     * Check if a feature flag is disabled
     *
     * @param string $flag The feature flag name
     * @param string|null $operationKey The operation key (optional)
     * @return bool True if the feature flag is disabled
     */
    public function isDisabled(string $flag, ?string $operationKey = null): bool
    {
        return !$this->getFlag($flag, $operationKey);
    }

    /**
     * Get all feature flags for an operation
     *
     * @param string $operationKey The operation key
     * @return array The feature flags
     */
    public function getOperationFlags(string $operationKey): array
    {
        // Check if the user has the capability for the operation's factor
        $factorName = $this->operationFactorMap[$operationKey] ?? null;
        if ($factorName !== null && !$this->capabilityManager->hasFactorCapability($factorName)) {
            $this->log("All flags for operation '$operationKey' are disabled due to missing capability for factor '$factorName'", 'WARNING');
            return [];
        }

        $flags = $this->globalFlags;

        if (isset($this->operationFlags[$operationKey])) {
            $flags = array_merge($flags, $this->operationFlags[$operationKey]);
        }

        return $flags;
    }

    /**
     * Get the factor name for an operation
     *
     * @param string $operationKey The operation key
     * @return string|null The factor name or null if not found
     */
    public function getOperationFactor(string $operationKey): ?string
    {
        return $this->operationFactorMap[$operationKey] ?? null;
    }

    /**
     * Check if the current user has the capability for an operation's factor
     *
     * @param string $operationKey The operation key
     * @return bool True if the user has the capability, false otherwise
     */
    public function hasOperationCapability(string $operationKey): bool
    {
        $factorName = $this->operationFactorMap[$operationKey] ?? null;

        if ($factorName === null) {
            // If we don't have a factor mapping, assume the user has the capability
            return true;
        }

        return $this->capabilityManager->hasFactorCapability($factorName);
    }

    /**
     * Clear the operation-factor-context mapping cache
     * This should be called when the plugin is updated or when operations are added/removed
     *
     * @return void
     */
    public function clearOperationFactorMapCache(): void
    {
        delete_transient(BaseConstants::OPTION_FEATURE_FLAG_OPERATION_FACTOR_CONTEXT_MAP);
        //$this->log('Operation-factor-context mapping cache cleared');
    }

    /**
     * Rebuild the operation-factor-context mapping cache
     * This forces a fresh scan of the Operations directory
     *
     * @return void
     */
    public function rebuildOperationFactorMapCache(): void
    {
        $this->clearOperationFactorMapCache();
        $this->operationFactorMap = [];
        $this->factorContextMap = [];
        $this->operationContextMap = [];
        $this->buildOperationFactorMap();
        //$this->log('Operation-factor-context mapping cache rebuilt');
    }

    /**
     * Get the context name for an operation
     *
     * @param string $operationKey The operation key
     * @return string|null The context name or null if not found
     */
    public function getOperationContext(string $operationKey): ?string
    {
        return $this->operationContextMap[$operationKey] ?? null;
    }

    /**
     * Get the context name for a factor
     *
     * @param string $factorName The factor name
     * @return string|null The context name or null if not found
     */
    public function getFactorContext(string $factorName): ?string
    {
        return $this->factorContextMap[$factorName] ?? null;
    }

    /**
     * Get the complete operation-factor-context mapping
     *
     * @return array The complete mapping
     */
    public function getOperationFactorContextMap(): array
    {
        $mapping = [];

        foreach ($this->operationFactorMap as $operationKey => $factorName) {
            $contextName = $this->operationContextMap[$operationKey] ?? null;

            $mapping[$operationKey] = [
                'factor' => $factorName,
                'context' => $contextName
            ];
        }

        return $mapping;
    }
}
