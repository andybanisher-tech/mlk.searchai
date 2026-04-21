<?
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

class mlk_searchai extends CModule
{
    var $MODULE_ID = "mlk.searchai";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_CSS;
    var $PARTNER_NAME = "ООО «Сталкер-Консалтинг»";
    var $PARTNER_URI = "https://stalker-consulting.ru";

    function __construct()
    {
        $arModuleVersion = array();
        include(__DIR__ . "/version.php");
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = GetMessage("SEARCHAI_MODULE_NAME");
        $this->MODULE_DESCRIPTION = GetMessage("SEARCHAI_MODULE_DESC");
        $this->PARTNER_NAME = GetMessage("SEARCHAI_PARTNER_NAME");
        $this->PARTNER_URI = GetMessage("SEARCHAI_PARTNER_URI");
    }

    function DoInstall()
    {
        global $DOCUMENT_ROOT, $APPLICATION;

        if (!ModuleManager::isModuleInstalled("iblock"))
        {
            $APPLICATION->ThrowException(GetMessage("SEARCHAI_NEED_IBLOCK"));
            return false;
        }

        $this->InstallFiles();
        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallDB();
        $this->InstallEvents();

        return true;
    }

    function DoUninstall()
    {
        $this->UnInstallDB();
        $this->UnInstallEvents();
        $this->UnInstallFiles();
        ModuleManager::unRegisterModule($this->MODULE_ID);
        return true;
    }

    function InstallDB()
    {
        global $DB, $DBType, $APPLICATION;
        $this->errors = $DB->RunSQLBatch(
            $_SERVER["DOCUMENT_ROOT"] . "/local/modules/" . $this->MODULE_ID . "/install/db/" . strtolower($DBType) . "/install.sql"
        );
        if ($this->errors !== false)
        {
            $APPLICATION->ThrowException(implode("", $this->errors));
            return false;
        }
        return true;
    }

    function UnInstallDB()
    {
        global $DB, $DBType, $APPLICATION;
        $this->errors = $DB->RunSQLBatch(
            $_SERVER["DOCUMENT_ROOT"] . "/local/modules/" . $this->MODULE_ID . "/install/db/" . strtolower($DBType) . "/uninstall.sql"
        );
        if ($this->errors !== false)
        {
            $APPLICATION->ThrowException(implode("", $this->errors));
            return false;
        }
        return true;
    }

    function InstallEvents()
    {
        $eventManager = EventManager::getInstance();
        $eventManager->registerEventHandler(
            "search",
            "OnSearchGetFoundRows",
            $this->MODULE_ID,
            "\\Mlk\\Searchai\\Events",
            "onSearchGetFoundRows"
        );
        return true;
    }

    function UnInstallEvents()
    {
        $eventManager = EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            "search",
            "OnSearchGetFoundRows",
            $this->MODULE_ID,
            "\\Mlk\\Searchai\\Events",
            "onSearchGetFoundRows"
        );
        return true;
    }

    function InstallFiles()
    {
        CopyDirFiles(
            $_SERVER["DOCUMENT_ROOT"] . "/local/modules/" . $this->MODULE_ID . "/install/admin",
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin",
            true,
            true
        );
        CopyDirFiles(
            $_SERVER["DOCUMENT_ROOT"] . "/local/modules/" . $this->MODULE_ID . "/install/components",
            $_SERVER["DOCUMENT_ROOT"] . "/local/components",
            true,
            true
        );
        return true;
    }

    function UnInstallFiles()
    {
        DeleteDirFiles(
            $_SERVER["DOCUMENT_ROOT"] . "/local/modules/" . $this->MODULE_ID . "/install/admin",
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin"
        );
        DeleteDirFiles(
            $_SERVER["DOCUMENT_ROOT"] . "/local/modules/" . $this->MODULE_ID . "/install/components",
            $_SERVER["DOCUMENT_ROOT"] . "/local/components/mlk"
        );
        return true;
    }
}
?>