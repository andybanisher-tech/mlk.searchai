<?
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\Directory; // Оставляем для deleteDirectory, если доступен

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

        // Агент очистки статистики
        \CAgent::AddAgent(
            '\\Mlk\\Searchai\\Agent::cleanOldData();',
            'mlk.searchai',
            'N',
            86400,
            '',
            'Y',
            '',
            30
        );

        return true;
    }

    function DoUninstall()
    {
        $this->UnInstallDB();
        $this->UnInstallEvents();
        $this->UnInstallFiles();
        \CAgent::RemoveModuleAgents('mlk.searchai');
        ModuleManager::unRegisterModule($this->MODULE_ID);
        return true;
    }

    function InstallDB()
    {
        global $DB, $DBType, $APPLICATION;
        $this->errors = $DB->RunSQLBatch(
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/install/db/" . strtolower($DBType) . "/install.sql"
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
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/install/db/" . strtolower($DBType) . "/uninstall.sql"
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
        $sourceComponents = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/install/components/mlk";
        $targetComponents = $_SERVER["DOCUMENT_ROOT"] . "/local/components/mlk";

        if (!is_dir($sourceComponents))
        {
            $GLOBALS["APPLICATION"]->ThrowException("Исходная папка компонентов не найдена: " . $sourceComponents);
            return false;
        }

        // Рекурсивное копирование без использования File::copyFile
        $this->recurseCopy($sourceComponents, $targetComponents);

        // Админские файлы
        $sourceAdmin = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/install/admin";
        $targetAdmin = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin";
        if (is_dir($sourceAdmin))
        {
            $this->recurseCopy($sourceAdmin, $targetAdmin);
        }

        return true;
    }

    function UnInstallFiles()
    {
        // Удаляем папку компонента
        $targetComponents = $_SERVER["DOCUMENT_ROOT"] . "/local/components/mlk/search.ai";
        if (is_dir($targetComponents))
        {
            // Используем рекурсивное удаление
            $this->recurseDelete($targetComponents);
        }

        // Удаляем админские файлы
        $sourceAdmin = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/install/admin";
        $targetAdmin = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin";
        if (is_dir($sourceAdmin))
        {
            $dir = opendir($sourceAdmin);
            while (false !== ($file = readdir($dir)))
            {
                if ($file != '.' && $file != '..')
                {
                    $targetFile = $targetAdmin . '/' . $file;
                    if (file_exists($targetFile))
                    {
                        unlink($targetFile);
                    }
                }
            }
            closedir($dir);
        }

        return true;
    }

    // Вспомогательная функция рекурсивного копирования через plain PHP
    private function recurseCopy($src, $dst)
    {
        if (!is_dir($dst))
        {
            mkdir($dst, 0755, true);
        }
        $dir = opendir($src);
        while (false !== ($file = readdir($dir)))
        {
            if (($file != '.') && ($file != '..'))
            {
                $srcFile = $src . '/' . $file;
                $dstFile = $dst . '/' . $file;
                if (is_dir($srcFile))
                {
                    $this->recurseCopy($srcFile, $dstFile);
                }
                else
                {
                    copy($srcFile, $dstFile);
                }
            }
        }
        closedir($dir);
    }

    // Вспомогательная функция рекурсивного удаления
    private function recurseDelete($dir)
    {
        if (!is_dir($dir)) return;
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item)
        {
            $path = $dir . '/' . $item;
            if (is_dir($path))
            {
                $this->recurseDelete($path);
            }
            else
            {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
?>