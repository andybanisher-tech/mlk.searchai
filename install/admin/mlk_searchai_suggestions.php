<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;

Loader::includeModule('mlk.searchai');

// Явно подключаем языковой файл
$langFile = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/mlk.searchai/lang/ru/admin/mlk_searchai_suggestions.php';
if (file_exists($langFile)) {
    require $langFile;
}

$connection = Application::getConnection();
$tableName = 'b_searchai_promoted_suggestions';

// Обработка индивидуального удаления по кнопке в строке (GET-запрос с параметрами action=delete&ID=...&sessid=...)
if (check_bitrix_sessid() && $_REQUEST['action'] == 'delete' && isset($_REQUEST['ID'])) {
    $id = (int)$_REQUEST['ID'];
    $connection->queryExecute("DELETE FROM {$tableName} WHERE ID = {$id}");
    LocalRedirect($APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID);
}

$APPLICATION->SetTitle(GetMessage('MLK_SEARCHAI_SUGGESTIONS_TITLE'));

$sTableID = 'tbl_mlk_searchai_suggestions';
$oSort = new CAdminSorting($sTableID, 'ID', 'desc');
$lAdmin = new CAdminList($sTableID, $oSort);

// Заголовки таблицы
$lAdmin->AddHeaders([
    ['id' => 'ID', 'content' => 'ID', 'sort' => 'ID', 'default' => true],
    ['id' => 'KEYWORD', 'content' => GetMessage('MLK_SEARCHAI_KEYWORD'), 'default' => true],
    ['id' => 'SUGGESTION', 'content' => GetMessage('MLK_SEARCHAI_SUGGESTION'), 'default' => true],
    ['id' => 'WEIGHT', 'content' => GetMessage('MLK_SEARCHAI_WEIGHT'), 'default' => true],
    ['id' => 'ACTIVE', 'content' => GetMessage('MLK_SEARCHAI_ACTIVE'), 'default' => true],
]);

// Получаем список подсказок
$listSql = "SELECT * FROM {$tableName} ORDER BY KEYWORD, WEIGHT DESC";
$res = $connection->query($listSql);

// Формируем строки и добавляем действия
while ($rowData = $res->fetch()) {
    $row = $lAdmin->AddRow($rowData['ID'], [
        'ID' => $rowData['ID'],
        'KEYWORD' => $rowData['KEYWORD'],
        'SUGGESTION' => $rowData['SUGGESTION'],
        'WEIGHT' => $rowData['WEIGHT'],
        'ACTIVE' => ($rowData['ACTIVE'] == 'Y') ? GetMessage('MLK_SEARCHAI_YES') : GetMessage('MLK_SEARCHAI_NO'),
    ]);

    // Действия для строки
    $editUrl = 'mlk_searchai_suggestion_edit.php?ID=' . $rowData['ID'] . '&lang=' . LANGUAGE_ID;
    $deleteUrl = $APPLICATION->GetCurPage() . '?action=delete&ID=' . $rowData['ID'] . '&' . bitrix_sessid_get() . '&lang=' . LANGUAGE_ID;

    $actions = [
        ['ICON' => 'edit', 'TEXT' => GetMessage('MLK_SEARCHAI_EDIT'), 'ACTION' => $lAdmin->ActionRedirect($editUrl)],
        ['ICON' => 'delete', 'TEXT' => GetMessage('MLK_SEARCHAI_DELETE'), 'ACTION' => "if(confirm('" . CUtil::JSEscape(GetMessage('MLK_SEARCHAI_DELETE_CONFIRM')) . "')) window.location='" . $deleteUrl . "';"],
    ];
    $row->AddActions($actions);
}

// Подвал с общим количеством
$totalCount = $connection->queryScalar("SELECT COUNT(*) FROM {$tableName}");
$lAdmin->AddFooter([
    ['title' => GetMessage('MLK_SEARCHAI_TOTAL'), 'value' => $totalCount],
]);

// Групповые действия
$lAdmin->AddGroupActionTable([
    'delete' => GetMessage('MLK_SEARCHAI_DELETE'),
]);

// Контекстное меню (добавить)
$lAdmin->AddAdminContextMenu([
    ['TEXT' => GetMessage('MLK_SEARCHAI_ADD'), 'LINK' => 'mlk_searchai_suggestion_edit.php?lang=' . LANGUAGE_ID],
]);

$lAdmin->CheckListMode();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
$lAdmin->DisplayList();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");