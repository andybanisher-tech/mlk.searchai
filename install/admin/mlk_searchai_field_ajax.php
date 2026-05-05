<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;

// Проверка прав администратора
if (!$USER->IsAdmin()) die();

Loader::includeModule('mlk.searchai');

$request = Application::getInstance()->getContext()->getRequest();
$iblockId = (int)$request->getPost('iblock_id');
$entityType = $request->getPost('entity_type'); // SECTION или ELEMENT

$result = [
    'standard_fields' => [],
    'properties' => []
];

if ($iblockId > 0 && in_array($entityType, ['SECTION', 'ELEMENT'])) {
    // Стандартные поля для разделов и элементов
    if ($entityType == 'SECTION') {
        $result['standard_fields'] = [
            'ID' => 'ID',
            'NAME' => 'Название',
            'CODE' => 'Символьный код',
            'XML_ID' => 'Внешний код',
            'ACTIVE' => 'Активность',
            'SORT' => 'Сортировка',
            'DESCRIPTION' => 'Описание',
        ];
    } else { // ELEMENT
        $result['standard_fields'] = [
            'ID' => 'ID',
            'NAME' => 'Название',
            'CODE' => 'Символьный код',
            'XML_ID' => 'Внешний код',
            'ACTIVE' => 'Активность',
            'DETAIL_TEXT' => 'Детальное описание',
            'PREVIEW_TEXT' => 'Анонс',
            'TAGS' => 'Теги',
        ];
    }

    // Пользовательские свойства инфоблока
    $rsProps = \Bitrix\Iblock\PropertyTable::getList([
        'filter' => ['=IBLOCK_ID' => $iblockId],
        'select' => ['ID', 'CODE', 'NAME', 'PROPERTY_TYPE'],
        'order' => ['SORT' => 'ASC', 'NAME' => 'ASC']
    ]);
    while ($prop = $rsProps->fetch()) {
        if (!empty($prop['CODE'])) {
            $result['properties'][] = [
                'code' => $prop['CODE'],
                'name' => $prop['NAME'],
                'type' => $prop['PROPERTY_TYPE']
            ];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($result);