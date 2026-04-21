<?
namespace Mlk\Searchai;

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\DB\SqlExpression;

class Events
{
    public static function onSearchGetFoundRows($arEvent)
    {
        // Будет использоваться для логирования поисковых запросов
        // Пока заглушка
        return true;
    }
}