<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions;

use App\Domain\Common\Entities\Settings\Setting;

/**
 * Class WPFlowEvaluateData
 * Contains data collected during a flow step completion
 */
class WPFlowEvaluateData extends Setting
{

    /** @var string The language being evaluated */
    public string $language = 'en';

    /** @var bool Whether the answer has been evaluated by AI */
    public bool $isEvaluated = false;

    /** @var bool The result of the AI evaluation */
    public bool $evaluationResult = false;

    /** @var string|null AI-generated feedback about the answer */
    public ?string $evaluationFeedback = '';

    /** @var string|null Raw data from the AI evaluation */
    public ?string $evaluationRawAIResult = '';

    /** @var string|null Raw data from the AI prompt used for evaluation */
    public ?string $evaluationRawAIPrompt = '';

    /** @var array Additional flow-specific metadata */
    public array $metadata = [];

    /** @var string|null The postal address of the business */
    public ?string $postalAddress = null;

    // Constructor with all default values
    /**
     * WPFlowEvaluateData constructor.
     * @param bool $isEvaluated
     * @param bool $evaluationResult
     * @param string|null $evaluationFeedback
     * @param string|null $evaluationRawAIResult
     * @param string|null $evaluationRawAIPrompt
     * @param array $metadata
     */
    public function __construct(
        bool $isEvaluated = false,
        bool $evaluationResult = false,
        ?string $evaluationFeedback = '',
        ?string $evaluationRawAIResult = '',
        ?string $evaluationRawAIPrompt = '',
        array $metadata = []
    ) {
        $this->isEvaluated = $isEvaluated;
        $this->evaluationResult = $evaluationResult;
        $this->evaluationFeedback = $evaluationFeedback;
        $this->evaluationRawAIResult = $evaluationRawAIResult;
        $this->evaluationRawAIPrompt = $evaluationRawAIPrompt;
        $this->metadata = $metadata;

        parent::__construct();
    }
}