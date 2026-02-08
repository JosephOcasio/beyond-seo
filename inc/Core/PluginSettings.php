<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use App\Domain\Integrations\WordPress\Plugin\Entities\WPSettings;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

/**
 * Class PluginSettings
 */
class PluginSettings {

    protected const PLUGIN_SETTINGS_CLASS = WPSettings::class;

	//======================================
	//======================================

	/** @var string The option key for database storage */
    protected string $option_key = BaseConstants::OPTION_PLUGIN_SETTINGS;

    protected ?object $settings = null;

	/** @var self|null Singleton instance */
	private static ?self $instance = null;

	/**
	 * Private constructor to enforce a Singleton pattern.
	 */
	private function __construct() {
        $this->settings = new stdClass();
		$this->load_settings();
	}

    /**
     * Get the Singleton instance.
     *
     * @param bool $autoload Whether to load settings from the database immediately.
     * @return self
     */
	public static function instance(bool $autoload = false): self {
		if (self::$instance === null) {
			self::$instance = new self();
            if ($autoload) {
                self::$instance->load_settings();
            }
		}
		return self::$instance;
	}

	/**
	 * Load settings from the database and assign them to properties.
	 */
	public function load_settings(): void {
		$saved_settings = get_option($this->option_key, []);
        if(empty($saved_settings)) {
            $this->reset_to_defaults();
            return;
        }
		foreach ($saved_settings as $key => $value) {
            $this->settings->{$key} = $value;
		}
	}

	/**
	 * Save all properties as independent keys in the database using Reflection API.
	 *
	 * Optimized to avoid saving unchanged values.
	 */
	private function save(): void {
		$settings = [];
		$reflection = new ReflectionClass(self::PLUGIN_SETTINGS_CLASS);
		foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			$key = $property->getName();
			$settings[$key] = $this->settings->{$key};
		}

		// Compare with existing settings
		$current_saved_settings = get_option($this->option_key, []);
		if ($settings !== $current_saved_settings) {
			update_option($this->option_key, $settings);
		}
	}

	/**
	 * Reset all properties to their default values using Reflection API.
	 */
	public function reset_to_defaults(): void {
		$reflection = new ReflectionClass(self::PLUGIN_SETTINGS_CLASS);
		foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			$property_name = $property->getName();
			$default_value = $property->getDefaultValue();
			$this->settings->{$property_name} = $default_value;
		}
		$this->save();
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key The key of the setting to retrieve.
	 * @return mixed|null The setting value, or null if not found.
	 */
	public function get(string $key): mixed {

        // First try exact match
        if (property_exists($this->settings, $key)) {
            return $this->settings->{$key};
        }

        // If exact match fails, try case-insensitive search
        $reflection = new ReflectionClass(self::PLUGIN_SETTINGS_CLASS);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            if (strcasecmp($propertyName, $key) === 0 && property_exists($this->settings, $propertyName)) {
                return $this->settings->{$propertyName};
            }
        }

        return null;
	}

    /**
     * Get a default setting value.
     *
     * @param string $key The key of the setting to retrieve.
     * @return mixed|null The default value of the setting, or null if not found.
     */
    public  function getDefault(string $key): mixed {
        $reflection = new ReflectionClass(self::PLUGIN_SETTINGS_CLASS);
        if ($reflection->hasProperty($key)) {
            $property = $reflection->getProperty($key);
            if ($property->isPublic()) {
                return $property->getDefaultValue();
            }
        }
        return null;
    }

	/**
	 * Set a specific setting value and save immediately.
	 *
	 * Only save to the database if the new value differs from the current value.
	 *
	 * @param string $key The key of the setting to update.
	 * @param mixed $value The new value for the setting.
	 */
	public function set(string $key, mixed $value): void {
		if (($this->settings?->{$key} ?? false) !== $value) {
			$this->settings->{$key} = $value;
			$this->save();
		}
	}

	/**
	 * Get all settings as an associative array.
	 *
	 * @return array All settings as key-value pairs.
	 */
	public function get_all(): array {
		$settings = [];
		$reflection = new ReflectionClass(self::PLUGIN_SETTINGS_CLASS);
		foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			$key = $property->getName();
            if(property_exists($this->settings, $key)) {
                $settings[$key] = $this->settings->{$key};
            }
		}
		return $settings;
	}

	/**
	 * Get the option key for database storage.
	 *
	 * @return string The option key.
	 */
	public function get_option_key(): string {
		return $this->option_key;
	}

    /**
     * Load available variables for SEO templates.
     *
     * This method populates the `variables` property with available WordPress variables.
     */
    public function load_variables(): void
    {
        if(property_exists($this->settings, 'variables')) {
            $this->settings->variables = WordpressHelpers::get_available_WPVariables();
        }
    }
}