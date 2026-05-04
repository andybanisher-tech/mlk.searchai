<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\GroupTable;

Loader::includeModule('mlk.searchai');

// Подключаем языковой файл
$langFile = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/mlk.searchai/lang/ru/admin/mlk_searchai_suggestion_edit.php';
if (file_exists($langFile)) {
    require $langFile;
}

$connection = Application::getConnection();
$tableName = 'b_searchai_promoted_suggestions';
$ID = (int)($_REQUEST['ID'] ?? 0);
$arFields = [
    'KEYWORD' => '',
    'SUGGESTION' => '',
    'WEIGHT' => 10,
    'ACTIVE' => 'Y',
    'USER_GROUPS' => '',
];

if ($ID > 0) {
    $row = $connection->query("SELECT * FROM {$tableName} WHERE ID = {$ID}")->fetch();
    if ($row) {
        $arFields = $row;
    }
}

$APPLICATION->SetTitle($ID > 0 ? GetMessage('MLK_SEARCHAI_EDIT_TITLE') : GetMessage('MLK_SEARCHAI_ADD_TITLE'));

// Получаем список всех групп для выбора
$groupsRes = GroupTable::getList(['select' => ['ID', 'NAME'], 'order' => ['ID' => 'ASC']]);
$arGroups = [];
while ($group = $groupsRes->fetch()) {
    $arGroups[$group['ID']] = $group['NAME'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && check_bitrix_sessid()) {
    $keyword = trim($_POST['KEYWORD'] ?? '');
    $suggestion = trim($_POST['SUGGESTION'] ?? '');
    $weight = (int)($_POST['WEIGHT'] ?? 10);
    $active = $_POST['ACTIVE'] == 'Y' ? 'Y' : 'N';
    $userGroups = implode(',', $_POST['USER_GROUPS'] ?? []);

    if (empty($keyword) || empty($suggestion)) {
        CAdminMessage::ShowMessage(GetMessage('MLK_SEARCHAI_ERROR_EMPTY_FIELDS'));
    } else {
        $sqlHelper = $connection->getSqlHelper();
        if ($ID > 0) {
            $connection->queryExecute("UPDATE {$tableName} SET KEYWORD='{$sqlHelper->forSql($keyword)}', SUGGESTION='{$sqlHelper->forSql($suggestion)}', WEIGHT={$weight}, ACTIVE='{$active}', USER_GROUPS='{$sqlHelper->forSql($userGroups)}' WHERE ID={$ID}");
        } else {
            $connection->queryExecute("INSERT INTO {$tableName} (KEYWORD, SUGGESTION, WEIGHT, ACTIVE, USER_GROUPS) VALUES ('{$sqlHelper->forSql($keyword)}', '{$sqlHelper->forSql($suggestion)}', {$weight}, '{$active}', '{$sqlHelper->forSql($userGroups)}')");
        }
        LocalRedirect('mlk_searchai_suggestions.php?lang=' . LANGUAGE_ID);
    }
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$aTabs = [
    ['DIV' => 'edit1', 'TAB' => GetMessage('MLK_SEARCHAI_TAB_MAIN'), 'TITLE' => GetMessage('MLK_SEARCHAI_TAB_MAIN_TITLE')],
];
$tabControl = new CAdminTabControl('tabControl', $aTabs);
?>
<form method="POST" action="<?= $APPLICATION->GetCurPage() ?>?lang=<?= LANGUAGE_ID ?><?= $ID > 0 ? '&ID='.$ID : '' ?>">
<?= bitrix_sessid_post() ?>
<? $tabControl->Begin(); ?>
<? $tabControl->BeginNextTab(); ?>
    <tr>
        <td width="40%"><?= GetMessage('MLK_SEARCHAI_FIELD_KEYWORD') ?>:</td>
        <td width="60%"><input type="text" name="KEYWORD" value="<?= htmlspecialcharsbx($arFields['KEYWORD']) ?>" size="40"></td>
    </tr>
    <tr>
        <td><?= GetMessage('MLK_SEARCHAI_FIELD_SUGGESTION') ?>:</td>
        <td><input type="text" name="SUGGESTION" value="<?= htmlspecialcharsbx($arFields['SUGGESTION']) ?>" size="40"></td>
    </tr>
    <tr>
        <td><?= GetMessage('MLK_SEARCHAI_FIELD_WEIGHT') ?>:</td>
        <td><input type="text" name="WEIGHT" value="<?= $arFields['WEIGHT'] ?>" size="10"></td>
    </tr>
    <tr>
        <td><?= GetMessage('MLK_SEARCHAI_FIELD_ACTIVE') ?>:</td>
        <td><input type="checkbox" name="ACTIVE" value="Y" <?= $arFields['ACTIVE'] == 'Y' ? 'checked' : '' ?>></td>
    </tr>
    <tr>
        <td><?= GetMessage('MLK_SEARCHAI_FIELD_USER_GROUPS') ?>:</td>
        <td>
            <select name="USER_GROUPS[]" multiple size="5">
                <option value="">&lt;не выбрано&gt;</option>
                <?php foreach ($arGroups as $groupId => $groupName): ?>
                    <?php $selected = in_array($groupId, explode(',', $arFields['USER_GROUPS'])) ? 'selected' : ''; ?>
                    <option value="<?= $groupId ?>" <?= $selected ?>><?= htmlspecialcharsbx($groupName) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
<? $tabControl->Buttons(); ?>
    <input type="submit" name="save" value="<?= GetMessage('MLK_SEARCHAI_SAVE') ?>" class="adm-btn-save">
    <a href="mlk_searchai_suggestions.php?lang=<?= LANGUAGE_ID ?>" class="adm-btn"><?= GetMessage('MLK_SEARCHAI_CANCEL') ?></a>
<? $tabControl->End(); ?>
</form>
<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");