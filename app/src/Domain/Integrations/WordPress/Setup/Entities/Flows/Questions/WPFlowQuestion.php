<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Questions;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions\WPFlowDataCompletions;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Steps\WPFlowStep;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Completions\InternalDBWPFlowCompletions;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Questions\InternalDBWPFlowQuestion;
use App\Domain\Integrations\WordPress\Setup\Services\WPFlowQuestionsService;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Infrastructure\Exceptions\InternalErrorException;

/**
 * Class WPFlowQuestion
 * @method static WPFlowQuestionsService getService()
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPFlowQuestion::class)]
class WPFlowQuestion extends Entity
{

    /** @var int|null ID of the parent question (for follow-up questions) */
    public ?int $parentId = null;

    /** @var int The ID of the flow step this question belongs to */
    public int $stepId;

    /** @var WPFlowStep|null The associated step */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB)]
    public ?WPFlowStep $step = null;

    /** @var string The text of the question */
    public string $question;

    /** @var int The sequence order of the question within the step */
    public int $sequence = 1;

    /** @var string|null Context information for AI evaluation */
    public ?string $aiContext = null;

    /** @var bool Whether this is an AI-generated follow-up question */
    public bool $isAiGenerated = false;

    /** @var WPFlowDataCompletions|null All completions for this question */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB, loadMethod: 'getCompletionsByQuestionId', repoClass: InternalDBWPFlowCompletions::class)]
    public ?WPFlowDataCompletions $completions = null;

    /**
     * Generates a follow-up question based on the answer to this question
     *
     * @param string $answer The answer to this question
     * @return WPFlowQuestion A new follow-up question entity
     * @throws InternalErrorException
     */
    public function generateFollowUpQuestion(string $answer): WPFlowQuestion
    {
        /** @var WPFlowQuestionsService $service */
        $service = self::getService();

        $prompt = $service->buildFollowUpPrompt($this, $answer);
        $result = $service->callAIService($prompt);

        $followUpQuestion = new WPFlowQuestion();
        $followUpQuestion->stepId = $this->stepId;
        $followUpQuestion->question = $result['followUpQuestion'] ?? __('Could you provide more details?', 'beyond-seo');
        $followUpQuestion->parentId = $this->id;
        $followUpQuestion->isAiGenerated = true;
        $followUpQuestion->aiContext = json_encode([
            'originalQuestion' => $this->question,
            'originalAnswer' => $answer,
            'reasonForFollowUp' => $result['reasonForFollowUp'] ?? null
        ]);

        return $followUpQuestion;
    }
}
