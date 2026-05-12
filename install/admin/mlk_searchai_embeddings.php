<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;

Loader::includeModule('mlk.searchai');
Loader::includeModule('iblock');

$langFile = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/mlk.searchai/lang/ru/admin/mlk_searchai_embeddings.php';
if (file_exists($langFile)) {
    require $langFile;
}

$connection = Application::getConnection();
$moduleId = 'mlk.searchai';
$iblockId = (int)Option::get($moduleId, 'iblock_id', 2);

$APPLICATION->SetTitle(GetMessage('MLK_SEARCHAI_EMBEDDINGS_TITLE'));

if (check_bitrix_sessid() && isset($_REQUEST['start_agent']) && $_REQUEST['start_agent'] === 'Y') {
    // Добавляем агента, если его ещё нет
    $agentName = '\\Mlk\\Searchai\\Agent::generateEmbeddings();';
    $rsAgent = \CAgent::GetList(['ID' => 'ASC'], ['NAME' => $agentName]);
    if (!$rsAgent->Fetch()) {
        \CAgent::AddAgent(
            $agentName,
            'mlk.searchai',
            'N',      // не периодический
            10,        // интервал 10 секунд
            '',        // дата начала (сразу)
            'Y',       // активен
            '',        // дата первого запуска
            30         // сортировка
        );
        CAdminMessage::ShowMessage(['TYPE' => 'OK', 'MESSAGE' => GetMessage('MLK_SEARCHAI_AGENT_STARTED')]);
    } else {
        CAdminMessage::ShowMessage(['TYPE' => 'OK', 'MESSAGE' => GetMessage('MLK_SEARCHAI_AGENT_ALREADY_RUNNING')]);
    }
}

$total = $connection->queryScalar("SELECT COUNT(*) FROM b_iblock_element WHERE IBLOCK_ID = {$iblockId} AND ACTIVE = 'Y'");
$indexed = $connection->queryScalar("SELECT COUNT(*) FROM b_searchai_embeddings");

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<p><?= GetMessage('MLK_SEARCHAI_EMBEDDINGS_INFO', ['#TOTAL#' => $total, '#INDEXED#' => $indexed]) ?></p>

<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="start_agent" value="Y">
    <input type="submit" value="<?= GetMessage('MLK_SEARCHAI_EMBEDDINGS_START_AGENT') ?>">
    <br><small><?= GetMessage('MLK_SEARCHAI_AGENT_HINT') ?></small>
</form>

<p>
    <?= GetMessage('MLK_SEARCHAI_AGENT_PROGRESS_INFO') ?>
</p>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");