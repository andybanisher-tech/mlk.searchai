<?

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$menu = [
    "parent_menu" => "global_menu_services",
    "section" => "mlk_searchai",
    "sort" => 100,
    "text" => Loc::getMessage("MLK_SEARCHAI_MENU_TEXT"),
    "title" => Loc::getMessage("MLK_SEARCHAI_MENU_TITLE"),
    "icon" => "mlk_searchai_menu_icon",
    "page_icon" => "mlk_searchai_page_icon",
    "items_id" => "menu_mlk_searchai",
    "items" => [
        [
            "text" => Loc::getMessage("MLK_SEARCHAI_MENU_ITEM_SETTINGS"),
            "url" => "settings.php?mid=mlk.searchai&lang=" . LANGUAGE_ID,
        ],
        [
            "text" => Loc::getMessage("MLK_SEARCHAI_MENU_ITEM_SUGGESTIONS"),
            "url" => "mlk_searchai_suggestions.php?lang=" . LANGUAGE_ID,
        ],
    ],
];

return $menu;
