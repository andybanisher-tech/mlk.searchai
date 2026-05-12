<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;

Loader::includeModule('mlk.searchai');
Loader::includeModule('iblock');
Loc::loadMessages(__FILE__);

$connection = Application::getConnection();
$moduleId = 'mlk.searchai';
$iblockId = (int)\Bitrix\Main\Config\Option::get($moduleId, 'iblock_id', 2);

$APPLICATION->SetTitle(Loc::getMessage('MLK_SEARCHAI_EMBEDDINGS_TITLE'));

if (check_bitrix_sessid() && isset($_REQUEST['generate']) && $_REQUEST['generate'] === 'Y') {
    $count = 0;
    $res = \CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
        false,
        false,
        ['ID', 'NAME', 'DETAIL_TEXT', 'PREVIEW_TEXT']
    );
    while ($el = $res->Fetch()) {
        $text = $el['NAME'];
        if (!empty($el['DETAIL_TEXT'])) {
            $text .= ' ' . $el['DETAIL_TEXT'];
        } elseif (!empty($el['PREVIEW_TEXT'])) {
            $text .= ' ' . $el['PREVIEW_TEXT'];
        }
        $emb = \Mlk\Searchai\Search\Embedder::getEmbedding($text);
        if ($emb) {
            \Mlk\Searchai\Search\Embedder::saveEmbedding($el['ID'], $emb);
            $count++;
        }
    }
    CAdminMessage::ShowMessage([
        'TYPE' => 'OK',
        'MESSAGE' => Loc::getMessage('MLK_SEARCHAI_EMBEDDINGS_GENERATED', ['#COUNT#' => $count])
    ]);
}

$total = $connection->queryScalar("SELECT COUNT(*) FROM b_iblock_element WHERE IBLOCK_ID = {$iblockId} AND ACTIVE = 'Y'");
$indexed = $connection->queryScalar("SELECT COUNT(*) FROM b_searchai_embeddings");

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<!-- Форма генерации эмбеддингов -->
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="generate" value="Y">

    <p><?= Loc::getMessage('MLK_SEARCHAI_EMBEDDINGS_INFO', ['#TOTAL#' => $total, '#INDEXED#' => $indexed]) ?></p>

    <input type="submit" value="<?= Loc::getMessage('MLK_SEARCHAI_EMBEDDINGS_START') ?>" class="adm-btn-save">
</form>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");