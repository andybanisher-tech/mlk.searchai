<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

global $APPLICATION;

$moduleId = 'your_partner_code.searchai';

$aMenu = [
    [
        'parent_menu' => 'global_menu_your_partner_code_searchai',
        'section' => 'searchai_settings',
        'title' => GetMessage('YOUR_PARTNER_CODE_SEARCHAI_SETTINGS'),
        'url' => 'options.php?mid=' . $moduleId . '&lang=' . LANGUAGE_ID,
        'icon' => 'searchai_settings_icon',
        'page_icon' => 'searchai_page_icon'
    ],
    [
        'parent_menu' => 'global_menu_your_partner_code_searchai',
        'section' => 'searchai_index',
        'title' => GetMessage('YOUR_PARTNER_CODE_SEARCHAI_INDEX'),
        'url' => 'index.php?lang=' . LANGUAGE_ID,
        'icon' => 'searchai_index_icon',
        'page_icon' => 'searchai_page_icon'
    ],
    [
        'parent_menu' => 'global_menu_your_partner_code_searchai',
        'section' => 'searchai_logs',
        'title' => GetMessage('YOUR_PARTNER_CODE_SEARCHAI_LOGS'),
        'url' => 'logs.php?lang=' . LANGUAGE_ID,
        'icon' => 'searchai_logs_icon',
        'page_icon' => 'searchai_page_icon'
    ]
];

$APPLICATION->AddSideMenu($aMenu);

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
