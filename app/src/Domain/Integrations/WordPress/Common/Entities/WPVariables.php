<?php
declare( strict_types=1 );

namespace App\Domain\Integrations\WordPress\Common\Entities;

use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\ObjectSet;
use DDD\Infrastructure\Traits\AfterConstruct\Attributes\AfterConstruct;
use Exception;

/**
 * Represents a list of variables that can be used in a WordPress template.
 * @method WPVariable getByUniqueKey(string $key)
 * @method WPVariable[] getElements()
 * @property App\Domain\Integrations\WordPress\Common\Entities\WPVariable[] $elements
 */
class WPVariables extends ObjectSet {

	/** @var WPVariable[] $elements The variables */
	public array $elements = [];

	/**
	 * Fixes the object type to match with remove entity.
	 * @return void
	 * @throws Exception
	 */
	#[AfterConstruct]
	public function fixObjectType(): void {
		if ( ! str_starts_with( $this->objectType, 'RankingCoach\\' ) ) {
			return;
		}
		$count = 1;
		$this->objectType = str_replace('RankingCoach\\', '', $this->objectType, $count);
	}

	/**
	 * Add a variable to the list.
	 *
	 * @param WPVariable $variable The variable to add.
	 */
	public function addVariable(WPVariable $variable): void {
		$this->add($variable);
		$variable->setParent($this);
	}

	/**
	 * Get a variable by key.
	 *
	 * @param string $key The variable key.
	 * @return DefaultObject|null
	 */
	public function getVariable(string $key): ?WPVariable {
		return $this->getByUniqueKey($key) ?? null;
	}

	/**
	 * Get all variables available in the given context.
	 *
	 * @param array $context The context for checking availability.
	 * @return WPVariable[]
	 */
	public function getAvailableVariables(array $context = []): array {
		return array_filter($this->elements, function ($variable) use ($context) {
			/** @var WPVariable $variable */
			return $variable->isAvailable($context);
		});
	}

	/**
	 * Get the unique key of the object.
	 *
	 * @return string
	 */
	public function uniqueKey(): string {
		$uniqueKey = '';
		foreach ($this->elements as $element) {
			$uniqueKey .= $element->uniqueKey();
		}
		return md5($uniqueKey);
	}
}