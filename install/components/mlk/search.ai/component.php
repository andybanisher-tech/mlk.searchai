<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;

Loader::includeModule('mlk.searchai');

$arResult['COMPONENT_ID'] = 'mlk_search_' . randString(5);
$arResult['PARAMS'] = [
    'iblockId' => $arParams['IBLOCK_ID'] ?? 2,
    'limit' => $arParams['RESULTS_LIMIT'] ?? 5,
    'showImages' => $arParams['SHOW_IMAGES'] ?? 'Y',
    'imageWidth' => $arParams['IMAGE_WIDTH'] ?? 40,
    'imageHeight' => $arParams['IMAGE_HEIGHT'] ?? 40,
    'searchPageUrl' => $arParams['SEARCH_PAGE_URL'] ?? '/catalog/',
    'aiEnabled' => Option::get('mlk.searchai', 'ai_feature_enabled', 'N'),
];

$this->IncludeComponentTemplate();