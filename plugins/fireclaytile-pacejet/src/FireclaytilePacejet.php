<?php
/**
 * Fireclay Tile Pacejet plugin for Craft CMS 3.x
 *
 * Pacejet provider for Postie
 *
 * @link      https://solspace.com
 * @copyright Copyright (c) 2021 Solspace
 */

namespace solspace\fireclaytilepacejet;

use Craft;
use yii\base\Event;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use verbb\postie\services\Providers;
use solspace\fireclaytilepacejet\providers\Pacejet;
use verbb\postie\events\RegisterProviderTypesEvent;

/**
 * Class FireclaytilePacejet
 *
 * @author     Solspace
 * @package    solspace\fireclaytilepacejet
 * @since      1.0.0
 */
class FireclaytilePacejet extends Plugin {
	/**
	 * @var FireclaytilePacejet
	 */
	public static $plugin;

	/**
	 * @var string
	 */
	public $schemaVersion = '1.0.0';

	/**
	 * @var bool
	 */
	public $hasCpSettings = false;

	/**
	 * @var bool
	 */
	public $hasCpSection = false;

	public function init() {
		parent::init();

		self::$plugin = $this;

		// Do something after we're installed
		Event::on(
			Plugins::class,
			Plugins::EVENT_AFTER_INSTALL_PLUGIN,
			function(PluginEvent $event) {
				if ($event->plugin === $this) {
					// We were just installed
				}
			}
		);

		// Registers the Pacejet as new shipping provider
		Event::on(
			Providers::class,
			Providers::EVENT_REGISTER_PROVIDER_TYPES,
			function(RegisterProviderTypesEvent $event) {
				$event->providerTypes[] = Pacejet::class;
			}
		);

		Craft::info(
			Craft::t('fireclaytile-pacejet', '{name} plugin loaded', [
				'name' => $this->name
			]),
			__METHOD__
		);
	}
}
