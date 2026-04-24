<?php
// include.php

// Подключаем автозагрузчик Composer, который лежит внутри модуля
$autoloadPath = __DIR__ . '/lib/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
