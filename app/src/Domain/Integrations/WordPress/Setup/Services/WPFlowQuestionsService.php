<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Services;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Questions\WPFlowQuestion;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Questions\WPFlowQuestions;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Questions\InternalDBWPFlowQuestion;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Questions\InternalDBWPFlowQuestions;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Services\Service;
use Doctrine\ORM\Mapping\MappingException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * Class WPFlowQuestionsService
 */
class WPFlowQuestionsService extends Service
{
    public const DEFAULT_ENTITY_CLASS = WPFlowQuestion::class;

    /**
     * Gets all questions for a specific step
     *
     * @param int $stepId The ID of the step
     * @return WPFlowQuestions|null Collection of questions
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws ReflectionException
     * @throws MappingException
     * @throws InvalidArgumentException
     */
    public function getQuestionsByStepId(int $stepId): ?WPFlowQuestions
    {
        $repo = new InternalDBWPFlowQuestions();
        return $repo->findByStepId($stepId, false);
    }

    /**
     * Gets a question by its ID
     *
     * @param int $questionId The ID of the question
     * @return WPFlowQuestion|null The question or null if not found
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws MappingException
     * @throws ReflectionException
     * @throws \Doctrine\DBAL\Exception
     * @throws InvalidArgumentException
     */
    public function getQuestionById(int $questionId): ?WPFlowQuestion
    {
        $repo = new InternalDBWPFlowQuestion();
        return $repo->find($questionId);
    }

    /**
     * Saves a question to the database
     *
     * @param WPFlowQuestion $question The question to save
     * @return WPFlowQuestion The saved question (with updated ID)
     */
    public function saveQuestion(WPFlowQuestion $question): WPFlowQuestion
    {
        $repo = new InternalDBWPFlowQuestion();
        return $repo->save($question);
    }

    /**
     * Gets follow-up questions for a parent question
     *
     * @param int $parentQuestionId The ID of the parent question
     * @return WPFlowQuestions Collection of follow-up questions
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws MappingException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function getFollowUpQuestions(int $parentQuestionId): WPFlowQuestions
    {
        $repo = new InternalDBWPFlowQuestions();
        return $repo->findFollowUpQuestions($parentQuestionId);
    }

    /**
     * Evaluates a user's answer to a question using AI
     *
     * @param WPFlowQuestion $question The question being answered
     * @param string $answer The user's answer
     * @return array The evaluation result containing success, feedback, and follow-up info
     * @throws InternalErrorException If AI service connection fails
     */
    public function evaluateAnswer(WPFlowQuestion $question, string $answer): array
    {

        $prompt = $this->buildEvaluationPrompt($question, $answer);
        $result = $this->callAIService($prompt);

        return [
            'success' => $result['success'] ?? false,
            'feedback' => $result['feedback'] ?? null,
            'needsFollowUp' => $result['needsFollowUp'] ?? false
        ];
    }

    /**
     * Builds a prompt for answer evaluation
     *
     * @param WPFlowQuestion $question The question
     * @param string $answer The user's answer
     * @return string The formatted prompt for the AI service
     */
    public function buildEvaluationPrompt(WPFlowQuestion $question, string $answer): string
    {
        // Build a clear prompt for the AI
        return "Evaluate the following answer to the question: \"{{ $question->question }}\"\n\n" .
            "Answer: \"{{ $answer }}\"\n\n" .
            "Context: {{ $question->aiContext }}\n\n" .
            'Determine if the answer is adequate and specific to the question. ' .
            'Return a JSON with the following keys: success (boolean), feedback (string), needsFollowUp (boolean).';
    }

    /**
     * Builds a prompt for generating a follow-up question
     *
     * @param WPFlowQuestion $question The original question
     * @param string $answer The user's answer
     * @return string The formatted prompt for the AI service
     */
    public function buildFollowUpPrompt(WPFlowQuestion $question, string $answer): string
    {
        return "Based on the following question and answer:\n\n" .
            "Question: \"{{ $question->question }}\"\n\n" .
            "Answer: \"{{ $answer }}\"\n\n" .
            "Context: {{ $question->aiContext }}\n\n" .
            'Generate a follow-up question that helps the user provide a more specific and detailed answer. ' .
            'Return a JSON with the following keys: followUpQuestion (string), reasonForFollowUp (string).';
    }

    /**
     * Calls the AI service with the given prompt
     *
     * @param string $prompt The prompt for the AI
     * @return array The result from the AI
     * @throws InternalErrorException If the AI service connection fails
     */
    public function callAIService(string $prompt): array
    {

        try {
            // Code to call RC repository to access the RC AI service

            return [
                'success' => true,
                'feedback' => 'The answer is partially adequate.',
                'needsFollowUp' => true,
                'followUpQuestion' => 'Could you elaborate more on...?',
                'reasonForFollowUp' => 'The answer is too general.'
            ];
        } catch (Exception $e) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new InternalErrorException('Error connecting to AI service: ' . $e->getMessage());
        }
    }
}
