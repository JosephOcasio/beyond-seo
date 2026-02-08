<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Trait SingletonManager
 */
trait SingletonManager {

    /**
     * Store instances per class.
     *
     * @var array<class-string, object>
     */
    private static array $instances = [];

    /**
     * Returns the singleton instance of the calling class.
     */
    public static function getInstance(): static {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }
        return self::$instances[$class];
    }

    /**
     * Prevent direct construction.
     */
    protected function __construct() {}

    /**
     * Prevent cloning.
     */
    final public function __clone(): void {}

    /**
     * Prevent unserialization.
     */
    final public function __wakeup(): void {}
}
