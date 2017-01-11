<?php

require __DIR__.'/../../../vendor/autoload.php';

return [
	'paths' => [
		'migrations' => __DIR__.'/migrations'
	],

	'environments' => [
		'default_database' => 'main',
		'default_migration_table' => 'phinxlog_bors_keywords',
		'main' => [
			'name' => \B2\Cfg::get('main_bors_db', 'BORS'),
			'connection' => driver_mysql::factory(\B2\Cfg::get('main_bors_db', 'BORS'))->connection(),
		]
	]
];
