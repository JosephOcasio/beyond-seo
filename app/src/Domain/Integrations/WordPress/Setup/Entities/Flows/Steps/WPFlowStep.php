<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Steps;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\WPFlowCollectors;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions\WPFlowDataCompletions;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Questions\WPFlowQuestion;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Questions\WPFlowQuestions;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Completions\InternalDBWPFlowCompletions;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Steps\InternalDBWPFlowStep;
use App\Domain\Integrations\WordPress\Setup\Services\WPFlowStepsService;
use App\Domain\Integrations\WordPress\Setup\Services\WPRequirementsService;
use App\Infrastructure\Services\AppService;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\MappingException;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * @property WPFlowSteps $parent
 * @method WPFlowSteps getParent()
 * @method static WPFlowStepsService getService()
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPFlowStep::class)]
class WPFlowStep extends Entity
{
    public const STEP_MAX_COUNT_COMPLETION = 1;

    /** @var string The name of the FlowStep */
    public string $step;

    /** @var string The requirements of the FlowStep */
    public string $requirements;

    /** @var int The priority of the FlowStep */
    public int $priority;

    /** @var WPFlowCollectors|null All collectors stack */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB)]
    public ?WPFlowCollectors $collectors = null;

    /** @var WPFlowDataCompletions|null All current step completions */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB, loadMethod: 'getCompletionByStepId', repoClass: InternalDBWPFlowCompletions::class)]
    public ?WPFlowDataCompletions $completions = null;

    /** @var WPFlowQuestions|null The questions associated with this step */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB, loadMethod: 'getQuestionsByStepId')]
    public ?WPFlowQuestions $questions = null;

    /** @var WPFlowQuestion|null The current question */
    public ?WPFlowQuestion $currentQuestion = null;

    /** @var bool $isFinalStep Is final step */
    public bool $isFinalStep = false;

    /** @var bool The active status of the FlowStep */
    public bool $active = true;

    /** @var bool The completion status */
    public bool $completed = false;

    /** @var int The user save count */
    public int $userSaveCount = 0;

    /**
     * Checks if the step has been completed
     *
     * @use $wpdb WordPress database object.
     * @use $wpdb->get_var Get a single variable from the database.
     *
     * @return bool True if the step has been completed, false otherwise
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function hasCompleted(): bool
    {
        // If the step is already completed, return true
        if ($this->completed) {
            return true;
        }

        // If the user has saved the step more than WPFlowStep::STEP_MAX_COUNT_COMPLETION times, we consider it as completed
        // But only if the completion count is greater or equal with 1,
        // This means that have at least one completion, even for same question
        if ($this->userSaveCount >= WPFlowStep::STEP_MAX_COUNT_COMPLETION && $this->completions->count() >= 1) {
            return true;
        }

        // Split the requirements string into an array, for easier processing
        $requirements = array_filter(array_map('trim', explode(',', $this->requirements)));

        if (empty($requirements)) {
            return false;
        }

        /** @var WPRequirementsService $wpRequirements */
        $wpRequirements = AppService::instance()->getService(WPRequirementsService::class);

        $requirementsValues = $wpRequirements->getRequirementsByName($requirements);

        // All requirements must be satisfied
        $this->completed = (count($requirementsValues) === count($requirements));
        return $this->completed;
    }

    /**
     * Checks if the step has a current question
     *
     * @return void
     */
    public function hasCurrentQuestion(): void
    {
        $previousQuestionIndex = $this->questions->count() - 1;
        $previousQuestion = $this->questions->getElements()[$previousQuestionIndex] ?? null;
        if (!$previousQuestion) {
            $this->currentQuestion = null;
            return;
        }
        $this->currentQuestion = $previousQuestion;
    }

    /**
     * Checks if the step has questions
     *
     * @return bool True if the step has questions, false otherwise
     */
    public function hasQuestions(): bool
    {
        return $this->questions !== null && !$this->questions->isEmpty();
    }

    /**
     * Retrieves the first question for this step
     *
     * @return WPFlowQuestion|null The first question or null if none exists
     */
    public function getFirstQuestion(): ?WPFlowQuestion
    {
        if (!$this->hasQuestions()) {
            return null;
        }

        return $this->questions->getElements()[0] ?? null;
    }

    /**
     * Retrieves the last question for this step
     *
     * @return WPFlowQuestion|null The last question or null if none exists
     */
    public function getLastQuestion(): ?WPFlowQuestion
    {
        if (!$this->hasQuestions()) {
            return null;
        }

        $questions = $this->questions->getElements();

        // Sort the questions array depending on the sequence property
        usort($questions, function (WPFlowQuestion $a, WPFlowQuestion $b) {
            return $b->sequence <=> $a->sequence;
        });

        // Return the question with the highest sequence value (first element after sorting).
        return $questions[0] ?? null;
    }

    /**
     * Get the next step after this one based on generated flow steps
     *
     * @return WPFlowStep|null
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ORMException
     * @throws ReflectionException
     * @throws ForbiddenException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     * @throws JsonException
     */
    public function getNextStep(): ?WPFlowStep
    {
        $stepId = (int)$this->id;

        /** @var WPFlowStepsService $wpFlowStepsService */
        $wpFlowStepsService = AppService::instance()->getService(WPFlowStepsService::class);
        $steps = $wpFlowStepsService->generateSteps(true);

        return $steps->getNextStep($stepId);
    }

    /**
     * Refreshes the step data
     *
     * @return WPFlowStep
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function refresh(): WPFlowStep
    {
        /** @var WPFlowStepsService $wpFlowStepsService */
        $wpFlowStepsService = AppService::instance()->getService(WPFlowStepsService::class);
        return $wpFlowStepsService->getStepById($this->id, true);
    }

    /**
     * Maps question keys to their corresponding texts
     *
     * @return array The mapping of question keys to texts
     */
    public function beyondseo_questions_map(): array
    {
        return [
            'onboarding_intro_start' => __("Let's get started.", 'beyondseo'),
            'onboarding_project_overview' => __('First, could you tell me what your website or project is about?', 'beyondseo'),
            'onboarding_ack_awesome' => __('Awesome!', 'beyondseo'),
            'onboarding_project_name' => __('Do you already have a name for your website, project, or business?', 'beyondseo'),
            'onboarding_ack_wonderful' => __('Wonderful!', 'beyondseo'),
            'onboarding_project_details' => __('Could you describe in more detail what you plan to do with your website? For example, will you offer products or services, share blog articles, or something else?', 'beyondseo'),
            'onboarding_ack_tasty' => __('Just tasty! Thanks for sharing!', 'beyondseo'),
            'onboarding_location_scope' => __('Is your project or business tied to a specific location? Do you serve customers locally, or operate in multiple areas?', 'beyondseo'),
            'onboarding_ack_i_see' => __('I see.', 'beyondseo'),
            'onboarding_target_area' => __("Where do you primarily want to focus your reach? Is there a particular city or region you'd like to target, or do you want to go nationwide?", 'beyondseo'),
            'onboarding_ack_thanks' => __('Thanks for providing that!', 'beyondseo'),
            'onboarding_project_uniqueness' => __("Lastly, is there anything else you'd like to highlight about your project or business, something that makes it unique or special?", 'beyondseo')
        ];
    }

    /**
     * Retrieves the question text based on a key
     *
     * @param string $key The question key
     * @return string|null The corresponding question text
     */
    public function beyondseo_get_question_text(string $key): ?string
    {
        $map = $this->beyondseo_questions_map();
        if(!$key || !isset($map[$key])) {
            return null;
        }
        $questionText = sprintf(
            /* translators: This is a question shown to the user */
            __('Q: %s', 'beyondseo'),
            $map[$key]
        );
        return str_replace('Q: ', '', $questionText) ?? $map[$key];
    }

    /**
     * Translates the question of the step
     *
     * @return void
     */
    public function translate(): void
    {
        /** @var WPFlowQuestions $questions */
        $questions = $this->questions;
        if ($questions === null || $questions->isEmpty()) {
            return;
        }
        /** @var WPFlowQuestion $question */
        foreach ($questions->getElements() as $question) {
            /* translators: This is a question shown to the user */
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            $question->question = __($question->question, 'beyond-seo');
        }
    }
}
