<?

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;

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

        if (!ModuleManager::isModuleInstalled("iblock")) {
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
        if ($this->errors !== false) {
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
        if ($this->errors !== false) {
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
        // Копирование компонента в /local/components/
        $sourceComponents = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/install/components/mlk";
        $targetComponents = $_SERVER["DOCUMENT_ROOT"] . "/local/components/mlk";

        if (!is_dir($sourceComponents)) {
            $GLOBALS["APPLICATION"]->ThrowException("Исходная папка компонентов не найдена: " . $sourceComponents);
            return false;
        }

        // Создаём целевую директорию с правами по умолчанию
        $dir = Directory::createDirectory($targetComponents);
        if (!$dir) {
            $GLOBALS["APPLICATION"]->ThrowException("Не удалось создать папку: " . $targetComponents);
            return false;
        }

        // Рекурсивное копирование
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceComponents, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $targetComponents . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                Directory::createDirectory($targetPath);
            } else {
                $sourceFile = $item->getPathname();
                File::copyFile($sourceFile, $targetPath);
            }
        }

        // Админские файлы (если есть)
        $sourceAdmin = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/install/admin";
        $targetAdmin = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin";
        if (is_dir($sourceAdmin)) {
            $iteratorAdmin = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceAdmin, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iteratorAdmin as $item) {
                $targetPath = $targetAdmin . DIRECTORY_SEPARATOR . $iteratorAdmin->getSubPathName();
                if ($item->isDir()) {
                    Directory::createDirectory($targetPath);
                } else {
                    File::copyFile($item->getPathname(), $targetPath);
                }
            }
        }

        return true;
    }

    function UnInstallFiles()
    {
        // Удаляем папку компонента
        $targetComponents = $_SERVER["DOCUMENT_ROOT"] . "/local/components/mlk/search.ai";
        if (is_dir($targetComponents)) {
            Directory::deleteDirectory($targetComponents);
        }

        // Удаляем админские файлы
        DeleteDirFiles(
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/install/admin",
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin"
        );

        return true;
    }
}
