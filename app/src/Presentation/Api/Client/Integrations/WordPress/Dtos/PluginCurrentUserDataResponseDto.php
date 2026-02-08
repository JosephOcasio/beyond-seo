<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use DDD\Presentation\Base\Dtos\RestResponseDto;

/**
 * Class PluginCurrentUserDataResponseDto
 *
 * @property string $ID
 * @property string $user_login
 * @property string $user_email
 * @property string[] $roles
 * @property string[] $capabilities
 * @property array[] $rankingcoach_data
 * @property OnboardingDataResponseDto|null $onboarding
 */
class PluginCurrentUserDataResponseDto extends RestResponseDto {

	public string $ID = '';
	public string $user_login = '';
	public string $user_email = '';
	public array $roles = [];
	public array $capabilities = [];
	public array $rankingcoach_data = [];
	public ?OnboardingDataResponseDto $onboarding = null;
}