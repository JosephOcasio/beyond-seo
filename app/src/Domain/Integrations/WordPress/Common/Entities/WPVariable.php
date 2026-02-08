<?php
declare( strict_types=1 );

namespace App\Domain\Integrations\WordPress\Common\Entities;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * Represents a variable that can be used in a WordPress template.
 * @method WPVariables getParent()
 */
class WPVariable extends ValueObject {

	/**
	 * The ID of the post.
	 * @var int|null
	 */
	public ?int $post_id;

	/**
	 * The variable key (e.g., 'post_title').
	 * @var string|null
	 */
	public ?string $key;

	/**
	 * The value of the variable for UI purposes (e.g., 'Post Title').
	 * @var string|null
	 */
	public ?string $value;
	
	/**
	 * The type of the variable (e.g., 'variable', 'separator', 'text').
	 * @var string|null
	 */
	public ?string $type = 'variable';

	/**
	 * The callback function to generate the variable's value.
	 * @var callable|null
	 */
	protected $resolver = null;

	/**
	 * The condition callback to determine if the variable is available (optional).
	 * @var callable|null
	 */
	protected $condition = null;

	/**
	 * Constructor.
	 *
	 * @param int|null $post_id The ID of the post.
	 * @param string|null $key The variable key.
	 * @param string|null $value The variable value.
	 * @param string|null $type The type of variable ('variable', 'separator', or 'text').
	 * @param callable|null $resolver A function to generate the variable value.
	 * @param callable|null $condition A function to check availability (optional).
	 */
	public function __construct(?int $post_id = null, ?string $key = null, ?string $value = null, ?string $type = 'variable', ?callable $resolver = null, ?callable $condition = null) {
		$this->post_id = $post_id;
		$this->key     = $key;
		$this->value = $value;
		$this->type = $type;
		$this->resolver = $resolver;
		$this->condition = $condition;
		parent::__construct();
	}

	/**
	 * Get the variable key.
	 *
	 * @return string
	 */
	public function getKey(): string {
		return $this->key;
	}

	/**
	 * Get the variable description.
	 *
	 * @return string
	 */
	public function getDescription(): string {
		return $this->value;
	}

	/**
	 * Check if the variable is available.
	 *
	 * @param array $context The context for evaluating the condition.
	 * @return bool
	 */
	public function isAvailable(array $context = []): bool {
		if ($this->condition === null) {
			return true;
		}
		return call_user_func($this->condition, $context);
	}

	/**
	 * Generate the variable's value.
	 *
	 * @param array $context The context for resolving.
	 * @return string
	 */
	public function resolve(array $context = []): string {
		return call_user_func($this->resolver, $context);
	}

	/**
	 * Get the unique key of the variable.
	 *
	 * @return string
	 * @noinspection PhpMissingParentCallCommonInspection
	 */
	public function uniqueKey(): string {
		return strtolower( $this->post_id . '_' . $this->key);
	}
}