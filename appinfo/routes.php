<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'sync#saveAccount', 'url' => '/account', 'verb' => 'PUT'],
		['name' => 'sync#getCalendars', 'url' => '/calendars', 'verb' => 'GET'],
		['name' => 'sync#saveSelection', 'url' => '/calendars', 'verb' => 'PUT'],
		['name' => 'sync#syncNow', 'url' => '/sync', 'verb' => 'POST'],
	],
];
