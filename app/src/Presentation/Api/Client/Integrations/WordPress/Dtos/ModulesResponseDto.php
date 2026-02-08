<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use DDD\Presentation\Base\Dtos\RestResponseDto;

/**
 * Class ModulesResponseDto
 * @property string[] $modules
 */
class ModulesResponseDto extends RestResponseDto {

	/**
     * @var string[] $modules
	 * The list of available modules.
     */
	public array $modules;
}