<?php

namespace YourPartnerCode\SearchAi;

class Events
{
    public static function OnSearchCatalogHandler(array $arQuery): array
    {
        // Handle search events from Bitrix search module
        $indexer = new Search\Indexer();
        
        // Add custom search results from AI index
        $customResults = $indexer->search(
            $arQuery['QUERY'],
            $arQuery['CNT'] ?? 10,
            $arQuery['SITE_ID'] ?? SITE_ID
        );

        return array_merge($arQuery['RESULT'] ?? [], $customResults);
    }

    public static function OnAdminMenu()
    {
        global $APPLICATION;
        
        return [
            [
                'parent_menu' => 'global_menu',
                'section' => 'your_partner_code_searchai',
                'title' => GetMessage('YOUR_PARTNER_CODE_SEARCHAI_MENU_TITLE'),
                'url' => '/admin/menu.php',
                'icon' => 'searchai_menu_icon',
                'page_icon' => 'searchai_page_icon',
                'items_id' => 'menu_your_partner_code_searchai'
            ]
        ];
    }

    public static function OnAfterIBlockElementAdd($elementId, $arFields)
    {
        $indexer = new Search\Indexer();
        $content = self::extractContentFromElement($elementId);
        
        if (!empty($content)) {
            $indexer->indexElement(
                $elementId,
                $arFields['SITE_ID'] ?? 's1',
                $content
            );
        }
    }

    public static function OnAfterIBlockElementUpdate($elementId, $arFields)
    {
        self::OnAfterIBlockElementAdd($elementId, $arFields);
    }

    public static function OnAfterIBlockElementDelete($elementId)
    {
        $db = \Bitrix\Main\Application::getConnection();
        $helper = $db->getSqlHelper();
        
        $db->queryExecute(
            "DELETE FROM b_searchai_index WHERE ELEMENT_ID = :element_id",
            ['element_id' => $elementId]
        );
    }

    protected static function extractContentFromElement(int $elementId): string
    {
        global $APPLICATION;
        
        $arElement = $APPLICATION->GetIBlockElements(
            ['ID' => $elementId],
            ['UF_*']
        );

        if (empty($arElement)) {
            return '';
        }

        $element = $arElement[0];
        $content = '';
        
        $content .= $element['NAME'] ?? '';
        $content .= ' ';
        $content .= $element['PREVIEW_TEXT'] ?? '';
        $content .= ' ';
        $content .= $element['DETAIL_TEXT'] ?? '';

        return $content;
    }
}
