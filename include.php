<?php
// include.php for mlk.searchai module

use Bitrix\Main\Loader;

// Регистрируем namespace для автозагрузки классов модуля
Loader::registerNamespace('Mlk\\Searchai', __DIR__ . '/lib');
