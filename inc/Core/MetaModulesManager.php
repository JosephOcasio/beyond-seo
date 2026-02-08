<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Traits\SingletonManager;

/**
 * Class MetaModulesManager
 *
 * This class is responsible for stores modules that have ability to generate content in meta head.
 */
class MetaModulesManager {

    use SingletonManager;

	public array $elements = [];


	/**
     * Adds a module to the list of modules.
     *
     * @param string $moduleName
     */
	public function addModule(string $moduleName): void {
		$this->elements[] = $moduleName;
	}

	/**
     * Returns all the registered meta head builder modules.
     *
     * @return string[]
     */
	public function getModules(): array {
		return array_unique($this->elements);
	}
}