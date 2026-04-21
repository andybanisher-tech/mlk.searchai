<?php
if (!defined("B_PROLOG_ADDED") || !defined("LOCAL_PATH")) {
    die("Access Denied");
}

class your_partner_code_searchai extends CModule
{
    var $MODULE_ID = "your_partner_code.searchai";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $PARTNER_NAME;
    var $PARTNER_URI;

    function __construct()
    {
        include(__DIR__ . "/version.php");
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = GetMessage("YOUR_PARTNER_CODE_SEARCHAI_MODULE_NAME");
        $this->MODULE_DESCRIPTION = GetMessage("YOUR_PARTNER_CODE_SEARCHAI_MODULE_DESCRIPTION");
        $this->PARTNER_NAME = "Your Partner Code";
        $this->PARTNER_URI = "https://yourpartnercode.com";
    }

    function InstallDB($dbName = '')
    {
        global $DB, $APPLICATION;
        $dbName = $dbName ?: $GLOBALS['DB']->GetCurrDB();
        $sqlFile = __DIR__ . "/db/mysql/install.sql";
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $sql = str_replace(["DB_NAME", "PREFIX_"], [$dbName, $GLOBALS['DB']->GetDBTableName("")] , $sql);
            $queries = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($queries as $query) {
                if (!empty($query) && !$DB->Query($query, false, "File: " . basename($sqlFile))) {
                    $APPLICATION->ThrowException($DB->LAST_ERROR);
                    return false;
                }
            }
        }
        return true;
    }

    function UnInstallDB()
    {
        global $DB, $APPLICATION;
        $sqlFile = __DIR__ . "/db/mysql/uninstall.sql";
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $queries = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($queries as $query) {
                if (!empty($query) && !$DB->Query($query, false, "File: " . basename($sqlFile))) {
                    $APPLICATION->ThrowException($DB->LAST_ERROR);
                    return false;
                }
            }
        }
        return true;
    }

    function InstallEvents()
    {
        global $APPLICATION;
        $eventManager = new CEventManager();
        $eventManager->AddEventHandler("search", "OnSearchCatalog", ["\YourPartnerCode\SearchAi\Events", "OnSearchCatalogHandler"]);
        return true;
    }

    function UnInstallEvents()
    {
        global $APPLICATION;
        $eventManager = new CEventManager();
        $eventManager->DeleteEventHandler("search", "OnSearchCatalog", ["\YourPartnerCode\SearchAi\Events", "OnSearchCatalogHandler"]);
        return true;
    }

    function DoInstall()
    {
        global $APPLICATION, $step;
        $this->InstallDB();
        $this->InstallEvents();
        RegisterModule($this->MODULE_ID);
        $APPLICATION->IncludeAdminFile(GetMessage("YOUR_PARTNER_CODE_SEARCHAI_INSTALL_TITLE"), __DIR__ . "/step1.php");
    }

    function DoUninstall()
    {
        global $APPLICATION, $step;
        $this->UnInstallDB();
        $this->UnInstallEvents();
        UnRegisterModule($this->MODULE_ID);
        $APPLICATION->IncludeAdminFile(GetMessage("YOUR_PARTNER_CODE_SEARCHAI_UNINSTALL_TITLE"), __DIR__ . "/step1.php");
    }
}
