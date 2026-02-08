<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Base\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use InvalidArgumentException;
use RankingCoach\Inc\Traits\SingletonManager;
use ReflectionClass;
use ReflectionException;

/**
 * Trait RcInstanceCreatorTrait
 */
trait RcInstanceCreatorTrait {

	/**
	 * Holds the instances of each class.
	 *
	 * @var array
	 */
	private static array $instances = [];

	/**
	 * Create or retrieve a Singleton instance of a class with the given parameters.
	 *
	 * @param string $className The fully qualified name of the class to instantiate.
	 * @param mixed ...$params Optional parameters to pass to the class constructor.
	 *
	 * @return object The Singleton instance of the specified class.
	 * @throws ReflectionException If the class does not exist, or there is an error in instantiation.
	 */
	public static function getInstance(string $className, ...$params): object {
		if (!class_exists($className)) {
			throw new InvalidArgumentException(esc_html("Class $className does not exist"));
		}

		$reflector = new ReflectionClass($className);

		$traits = $reflector->getTraitNames();
		if (in_array(SingletonManager::class, $traits)) {
			return $className::getInstance();
		}

		// Check if the instance already exists
		if (!isset(self::$instances[$className])) {
			// Create a new instance with the provided parameters and store it
			self::$instances[$className] = $reflector->newInstanceArgs($params);
		}

		// Return the stored instance
		return self::$instances[$className];
	}

	/**
	 * Create an instance of a class with the given parameters
	 *
	 * @param string $className
	 * @param mixed ...$params
	 *
	 * @return object
	 * @throws ReflectionException
	 */
	public static function createInstance(string $className, ...$params): object {
		if (!class_exists($className)) {
			throw new InvalidArgumentException(esc_html("Class $className does not exist"));
		}

		$reflector = new ReflectionClass($className);
		return $reflector->newInstanceArgs($params);
	}
}