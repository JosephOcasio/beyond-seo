<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Helpers\Attributes;

use Attribute;

/**
 * Class RcDocumentation
 * @package RankingCoach\Inc\Core\Helpers\Atributtes
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RcDocumentation {
	/**
	 * @var string
	 */
	public string $requestDto;

	/**
	 * @var string
	 */
	public string $responseDto;

	/**
	 * @var string
	 */
	public string $description;

	/**
	 * @var string
	 */
	public string $summary;

	/**
	 * RcDocumentation constructor.
	 *
	 * @param string $requestDto Fully qualified class name of the request DTO
	 * @param string $responseDto Fully qualified class name of the response DTO
	 * @param string $description
	 * @param string $summary
	 */
	public function __construct(
		string $requestDto = '',
		string $responseDto = '',
		string $description = '',
		string $summary = ''
	) {
		$this->requestDto = $requestDto;
		$this->responseDto = $responseDto;
		$this->description = $description;
		$this->summary = $summary;
	}

	/**
	 * Get the request DTO class name.
	 *
	 * @return string
	 */
	public function getRequestDto(): string
	{
		return $this->requestDto;
	}

	/**
	 * Get the response DTO class name.
	 *
	 * @return string
	 */
	public function getResponseDto(): string
	{
		return $this->responseDto;
	}

	/**
	 * Get the description.
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * Get the summary.
	 *
	 * @return string
	 */
	public function getSummary(): string
	{
		return $this->summary;
	}
}