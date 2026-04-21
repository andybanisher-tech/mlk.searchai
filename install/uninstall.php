<?php
if (!defined("B_PROLOG_ADDED") || !defined("LOCAL_PATH")) {
    die("Access Denied");
}

global $APPLICATION;

if (check_bitrix_sessid()) {
    $module = new mlk_searchai();
    
    if ($module->UnInstallDB()) {
        $module->UnInstallEvents();
        UnRegisterModule("mlk.searchai");
        $APPLICATION->IncludeAdminFile(GetMessage("mlk_SEARCHAI_UNINSTALL_COMPLETE"), __DIR__ . "/uninstall.php");
    } else {
        $APPLICATION->ThrowException($APPLICATION->GetException());
    }
}
