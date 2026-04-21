<?php
if (!defined("B_PROLOG_ADDED") || !defined("LOCAL_PATH")) {
    die("Access Denied");
}

global $APPLICATION;

if (check_bitrix_sessid()) {
    $module = new your_partner_code_searchai();
    
    if ($module->UnInstallDB()) {
        $module->UnInstallEvents();
        UnRegisterModule("your_partner_code.searchai");
        $APPLICATION->IncludeAdminFile(GetMessage("YOUR_PARTNER_CODE_SEARCHAI_UNINSTALL_COMPLETE"), __DIR__ . "/uninstall.php");
    } else {
        $APPLICATION->ThrowException($APPLICATION->GetException());
    }
}
