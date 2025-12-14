<?php

declare(strict_types=1);

namespace WPR\Republisher\Core;

/**
 * Register all actions and filters for the plugin
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Core
 */

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Core
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Loader {

	/**
	 * The array of actions registered with WordPress.
	 *
	 * @since    1.0.0
	 * @var      array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	protected array $actions = [];

	/**
	 * The array of filters registered with WordPress.
	 *
	 * @since    1.0.0
	 * @var      array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	protected array $filters = [];

	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string $hook          The name of the WordPress action that is being registered.
	 * @param    object $component     A reference to the instance of the object on which the action is defined.
	 * @param    string $callback      The name of the function definition on the $component.
	 * @param    int    $priority      Optional. The priority at which the function should be fired. Default is 10.
	 * @param    int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_action(
		string $hook,
		object $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string $hook          The name of the WordPress filter that is being registered.
	 * @param    object $component     A reference to the instance of the object on which the filter is defined.
	 * @param    string $callback      The name of the function definition on the $component.
	 * @param    int    $priority      Optional. The priority at which the function should be fired. Default is 10.
	 * @param    int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_filter(
		string $hook,
		object $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * A utility function that is used to register the actions and hooks into a single
	 * collection.
	 *
	 * @since    1.0.0
	 * @param    array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}> $hooks         The collection of hooks.
	 * @param    string                                                                                                  $hook          The name of the WordPress hook.
	 * @param    object                                                                                                  $component     A reference to the instance of the object.
	 * @param    string                                                                                                  $callback      The name of the callback function.
	 * @param    int                                                                                                     $priority      The priority for the hook.
	 * @param    int                                                                                                     $accepted_args The number of arguments the callback accepts.
	 * @return   array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	private function add(
		array $hooks,
		string $hook,
		object $component,
		string $callback,
		int $priority,
		int $accepted_args
	): array {
		$hooks[] = [
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		];

		return $hooks;
	}

	/**
	 * Register the filters and actions with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], [ $hook['component'], $hook['callback'] ], $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], [ $hook['component'], $hook['callback'] ], $hook['priority'], $hook['accepted_args'] );
		}
	}
}
