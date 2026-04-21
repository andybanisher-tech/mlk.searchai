<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

global $APPLICATION;

$moduleId = 'mlk.searchai';

$aMenu = [
    [
        'parent_menu' => 'global_menu_mlk_searchai',
        'section' => 'searchai_settings',
        'title' => GetMessage('MLK_SEARCHAI_SETTINGS'),
        'url' => 'options.php?mid=' . $moduleId . '&lang=' . LANGUAGE_ID,
        'icon' => 'searchai_settings_icon',
        'page_icon' => 'searchai_page_icon'
    ],
    [
        'parent_menu' => 'global_menu_mlk_searchai',
        'section' => 'searchai_index',
        'title' => GetMessage('MLK_SEARCHAI_INDEX'),
        'url' => 'index.php?lang=' . LANGUAGE_ID,
        'icon' => 'searchai_index_icon',
        'page_icon' => 'searchai_page_icon'
    ],
    [
        'parent_menu' => 'global_menu_mlk_searchai',
        'section' => 'searchai_logs',
        'title' => GetMessage('MLK_SEARCHAI_LOGS'),
        'url' => 'logs.php?lang=' . LANGUAGE_ID,
        'icon' => 'searchai_logs_icon',
        'page_icon' => 'searchai_page_icon'
    ]
];

$APPLICATION->AddSideMenu($aMenu);

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
