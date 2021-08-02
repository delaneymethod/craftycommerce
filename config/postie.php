<?php

return [
	// Global settings
	'*' => [
		'enableCaching' => true,
		'displayDebug' => false,
		'displayErrors' => false,
	],

	// Dev environment settings
	'local' => [
		'enableCaching' => false,
	],

	// Staging environment settings
	'development' => [],

	// Staging environment settings
	'staging' => [],

	// Production environment settings
	'production' => [],
];
