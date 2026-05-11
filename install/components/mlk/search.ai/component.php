<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Highloadblock\HighloadBlockTable;

Loader::includeModule('mlk.searchai');

// Получаем partner_id текущего пользователя
$partnerId = '';
global $USER;
if ($USER->IsAuthorized()) {
    $rsUser = CUser::GetByID($USER->GetID());
    if ($arUser = $rsUser->Fetch()) {
        $contragentId = (int)($arUser['UF_SELECTED_CONTRAGENT'] ?? 0);
        if ($contragentId > 0) {
            // Подключаем модуль highloadblock, если не подключен
            if (Loader::includeModule('highloadblock')) {
                try {
                    $hlblock = HighloadBlockTable::getById(7)->fetch();
                    if ($hlblock) {
                        $entity = HighloadBlockTable::compileEntity($hlblock);
                        $query = new \Bitrix\Main\Entity\Query($entity);
                        $query->setSelect(['UF_CODE']);
                        $query->setFilter(['=ID' => $contragentId]);
                        $result = $query->exec();
                        if ($row = $result->fetch()) {
                            $partnerId = $row['UF_CODE'] ?? '';
                        }
                    }
                } catch (\Exception $e) {
                    // Ошибка – оставим пустым
                }
            }
        }
    }
}

// Отладочный вывод (уберите потом)
echo '<!-- DEBUG: partnerId = ' . htmlspecialchars($partnerId) . ' -->';

$arResult['COMPONENT_ID'] = 'mlk_search_' . randString(5);
$arResult['PARAMS'] = [
    'iblockId' => $arParams['IBLOCK_ID'] ?? 2,
    'limit' => $arParams['RESULTS_LIMIT'] ?? 5,
    'showImages' => $arParams['SHOW_IMAGES'] ?? 'Y',
    'imageWidth' => $arParams['IMAGE_WIDTH'] ?? 40,
    'imageHeight' => $arParams['IMAGE_HEIGHT'] ?? 40,
    'searchPageUrl' => $arParams['SEARCH_PAGE_URL'] ?? '/catalog/',
    'aiEnabled' => Option::get('mlk.searchai', 'ai_feature_enabled', 'N'),
    'partnerId' => $partnerId,
];

$this->IncludeComponentTemplate();