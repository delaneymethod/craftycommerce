<?php
/**
 * Yii Application Config
 *
 * Edit this file at your own risk!
 *
 * The array returned by this file will get merged with
 * vendor/craftcms/cms/src/config/app.php and app.[web|console].php, when
 * Craft's bootstrap script is defining the configuration for the entire
 * application.
 *
 * You can define custom modules and system components, and even override the
 * built-in system components.
 *
 * If you want to modify the application config for *only* web requests or
 * *only* console requests, create an app.web.php or app.console.php file in
 * your config/ folder, alongside this one.
 */

use craft\helpers\App;
use craft\mail\transportadapters\Smtp;

return [
	// Global settings
	'*' => [
		'id' => App::env('APP_ID') ?: 'CraftCMS',

		'modules' => [],

		'bootstrap' => [],
	],

	// Dev environment settings
	'dev' => [
		'modules' => [],

		'bootstrap' => [],

		'components' => [
			'mailer' => function() {
				// Get the stored email settings
				$settings = App::mailSettings();

				// Override the transport adapter class
				$settings->transportType = Smtp::class;

				// Override the transport adapter settings
				$settings->transportSettings = [
					'useAuthentication' => true,
					'host' => App::env('SMTP_HOST'),
					'port' => App::env('SMTP_PORT'),
					'username' => App::env('SMTP_USERNAME'),
					'password' => App::env('SMTP_PASSWORD'),
				];

				// Create a Mailer component config with these settings
				$config = App::mailerConfig($settings);

				// Instantiate and return it
				return Craft::createObject($config);
			},
		],
	],

	// Staging environment settings
	'staging' => [],

	// Production environment settings
	'production' => [],
];
