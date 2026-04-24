<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;

Loader::includeModule('mlk.searchai');

// Передаём параметры в JavaScript
$arResult['COMPONENT_ID'] = 'mlk_search_' . randString(5);
$arResult['PARAMS'] = [
    'iblockId' => $arParams['IBLOCK_ID'] ?? 2,
    'limit' => $arParams['RESULTS_LIMIT'] ?? 5,
];

$this->IncludeComponentTemplate();