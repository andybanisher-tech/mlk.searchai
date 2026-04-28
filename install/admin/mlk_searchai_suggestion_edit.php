<?
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;

Loader::includeModule('mlk.searchai');

// Явно загружаем языковой файл из модуля
Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/mlk.searchai/lang/ru/admin/mlk_searchai_suggestion_edit.php');

$connection = Application::getConnection();
$tableName = 'b_searchai_promoted_suggestions';
$ID = (int)($_REQUEST['ID'] ?? 0);
$arFields = [
    'KEYWORD' => '',
    'SUGGESTION' => '',
    'WEIGHT' => 10,
    'ACTIVE' => 'Y',
];

if ($ID > 0) {
    $row = $connection->query("SELECT * FROM {$tableName} WHERE ID = {$ID}")->fetch();
    if ($row) {
        $arFields = $row;
    }
}

$APPLICATION->SetTitle($ID > 0 ? Loc::getMessage('MLK_SEARCHAI_EDIT_TITLE') : Loc::getMessage('MLK_SEARCHAI_ADD_TITLE'));

if ($_SERVER['REQUEST_METHOD'] == 'POST' && check_bitrix_sessid()) {
    $keyword = trim($_POST['KEYWORD'] ?? '');
    $suggestion = trim($_POST['SUGGESTION'] ?? '');
    $weight = (int)($_POST['WEIGHT'] ?? 10);
    $active = $_POST['ACTIVE'] == 'Y' ? 'Y' : 'N';

    if (empty($keyword) || empty($suggestion)) {
        CAdminMessage::ShowMessage(Loc::getMessage('MLK_SEARCHAI_ERROR_EMPTY_FIELDS'));
    } else {
        if ($ID > 0) {
            $connection->queryExecute("UPDATE {$tableName} SET KEYWORD='" . $connection->getSqlHelper()->forSql($keyword) . "', SUGGESTION='" . $connection->getSqlHelper()->forSql($suggestion) . "', WEIGHT={$weight}, ACTIVE='{$active}' WHERE ID={$ID}");
        } else {
            $connection->queryExecute("INSERT INTO {$tableName} (KEYWORD, SUGGESTION, WEIGHT, ACTIVE) VALUES ('" . $connection->getSqlHelper()->forSql($keyword) . "', '" . $connection->getSqlHelper()->forSql($suggestion) . "', {$weight}, '{$active}')");
        }
        LocalRedirect('mlk_searchai_suggestions.php?lang=' . LANGUAGE_ID);
    }
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

$aTabs = [
    ['DIV' => 'edit1', 'TAB' => Loc::getMessage('MLK_SEARCHAI_TAB_MAIN'), 'TITLE' => Loc::getMessage('MLK_SEARCHAI_TAB_MAIN_TITLE')],
];
$tabControl = new CAdminTabControl('tabControl', $aTabs);
?>
<form method="POST" action="<?= $APPLICATION->GetCurPage() ?>?lang=<?= LANGUAGE_ID ?><?= $ID > 0 ? '&ID=' . $ID : '' ?>">
    <?= bitrix_sessid_post() ?>
    <? $tabControl->Begin(); ?>
    <? $tabControl->BeginNextTab(); ?>
    <tr>
        <td width="40%"><?= Loc::getMessage('MLK_SEARCHAI_FIELD_KEYWORD') ?>:</td>
        <td width="60%"><input type="text" name="KEYWORD" value="<?= htmlspecialcharsbx($arFields['KEYWORD']) ?>" size="40"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('MLK_SEARCHAI_FIELD_SUGGESTION') ?>:</td>
        <td><input type="text" name="SUGGESTION" value="<?= htmlspecialcharsbx($arFields['SUGGESTION']) ?>" size="40"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('MLK_SEARCHAI_FIELD_WEIGHT') ?>:</td>
        <td><input type="text" name="WEIGHT" value="<?= $arFields['WEIGHT'] ?>" size="10"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('MLK_SEARCHAI_FIELD_ACTIVE') ?>:</td>
        <td><input type="checkbox" name="ACTIVE" value="Y" <?= $arFields['ACTIVE'] == 'Y' ? 'checked' : '' ?>></td>
    </tr>
    <? $tabControl->Buttons(); ?>
    <input type="submit" name="save" value="<?= Loc::getMessage('MLK_SEARCHAI_SAVE') ?>" class="adm-btn-save">
    <a href="mlk_searchai_suggestions.php?lang=<?= LANGUAGE_ID ?>" class="adm-btn"><?= Loc::getMessage('MLK_SEARCHAI_CANCEL') ?></a>
    <? $tabControl->End(); ?>
</form>
<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
