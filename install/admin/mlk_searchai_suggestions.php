<?
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;

Loader::includeModule('mlk.searchai');
Loc::loadMessages(__FILE__); // Теперь будет искать в lang/ru/admin/ модуля

$connection = Application::getConnection();
$tableName = 'b_searchai_promoted_suggestions';

if (check_bitrix_sessid() && isset($_REQUEST['action']) && $_REQUEST['action'] == 'delete' && isset($_REQUEST['ID'])) {
    $id = (int)$_REQUEST['ID'];
    $connection->queryExecute("DELETE FROM {$tableName} WHERE ID = {$id}");
    LocalRedirect($APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID);
}

$APPLICATION->SetTitle(Loc::getMessage('MLK_SEARCHAI_SUGGESTIONS_TITLE'));

$listSql = "SELECT * FROM {$tableName} ORDER BY KEYWORD, WEIGHT DESC";
$res = $connection->query($listSql);

$sTableID = 'tbl_mlk_searchai_suggestions';
$oSort = new CAdminSorting($sTableID, 'ID', 'desc');
$lAdmin = new CAdminList($sTableID, $oSort);

$lAdmin->AddHeaders([
    ['id' => 'ID', 'content' => 'ID', 'sort' => 'ID', 'default' => true],
    ['id' => 'KEYWORD', 'content' => Loc::getMessage('MLK_SEARCHAI_KEYWORD'), 'default' => true],
    ['id' => 'SUGGESTION', 'content' => Loc::getMessage('MLK_SEARCHAI_SUGGESTION'), 'default' => true],
    ['id' => 'WEIGHT', 'content' => Loc::getMessage('MLK_SEARCHAI_WEIGHT'), 'default' => true],
    ['id' => 'ACTIVE', 'content' => Loc::getMessage('MLK_SEARCHAI_ACTIVE'), 'default' => true],
]);

while ($row = $res->fetch()) {
    $row['ACTIVE'] = $row['ACTIVE'] == 'Y' ? Loc::getMessage('MLK_SEARCHAI_YES') : Loc::getMessage('MLK_SEARCHAI_NO');
    $row['EDIT_URL'] = 'mlk_searchai_suggestion_edit.php?ID=' . $row['ID'] . '&lang=' . LANGUAGE_ID;
    $row['DELETE_URL'] = $APPLICATION->GetCurPage() . '?action=delete&ID=' . $row['ID'] . '&' . bitrix_sessid_get() . '&lang=' . LANGUAGE_ID;
    $row['DELETE_CONFIRM'] = Loc::getMessage('MLK_SEARCHAI_DELETE_CONFIRM');
    $lAdmin->AddRow($row['ID'], $row);
}

$totalCount = $connection->queryScalar("SELECT COUNT(*) FROM {$tableName}");
$lAdmin->AddFooter([
    ['title' => Loc::getMessage('MLK_SEARCHAI_TOTAL'), 'value' => $totalCount],
]);

$lAdmin->AddGroupActionTable([
    'delete' => Loc::getMessage('MLK_SEARCHAI_DELETE'),
]);

$lAdmin->AddAdminContextMenu([
    ['TEXT' => Loc::getMessage('MLK_SEARCHAI_ADD'), 'LINK' => 'mlk_searchai_suggestion_edit.php?lang=' . LANGUAGE_ID],
]);

$lAdmin->CheckListMode();

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
$lAdmin->DisplayList();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
