<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;

Loader::includeModule('mlk.searchai');
Loc::loadMessages(__FILE__);

$connection = Application::getConnection();

$APPLICATION->SetTitle(Loc::getMessage('MLK_SEARCHAI_GENERATE_EMBEDDINGS_TITLE'));

if ($_SERVER['REQUEST_METHOD'] == 'POST' && check_bitrix_sessid()) {
    $iblockId = (int)($_POST['iblock_id'] ?? 2);
    $offset = (int)($_POST['offset'] ?? 0);
    $limit = 10; // обрабатываем по 10 товаров за раз

    $res = \CIBlockElement::GetList(
        ['ID' => 'ASC'],
        ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
        false,
        ['nOffset' => $offset, 'nTopCount' => $limit],
        ['ID', 'NAME', 'DETAIL_TEXT', 'PREVIEW_TEXT']
    );

    $processed = 0;
    while ($el = $res->Fetch()) {
        $text = $el['NAME'];
        if (!empty($el['DETAIL_TEXT'])) $text .= ' ' . $el['DETAIL_TEXT'];
        elseif (!empty($el['PREVIEW_TEXT'])) $text .= ' ' . $el['PREVIEW_TEXT'];

        $emb = \Mlk\Searchai\Search\Embedder::getEmbedding($text);
        if ($emb) {
            \Mlk\Searchai\Search\Embedder::saveEmbedding($el['ID'], $emb);
            $processed++;
        }
    }

    $total = \CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'], []);
    $totalCount = (int)$total;

    $nextOffset = $offset + $limit;
    if ($nextOffset >= $totalCount) {
        CAdminMessage::ShowMessage(Loc::getMessage('MLK_SEARCHAI_GENERATE_COMPLETED', ['#COUNT#' => $processed]));
    } else {
        CAdminMessage::ShowMessage(Loc::getMessage('MLK_SEARCHAI_GENERATE_PROGRESS', [
            '#PROCESSED#' => $nextOffset,
            '#TOTAL#' => $totalCount,
        ]));
        ?>
        <form method="post">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="iblock_id" value="<?= $iblockId ?>">
            <input type="hidden" name="offset" value="<?= $nextOffset ?>">
            <input type="submit" value="<?= Loc::getMessage('MLK_SEARCHAI_CONTINUE') ?>" class="adm-btn-save">
        </form>
        <?
        require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
        return;
    }
}

?>
<form method="post">
    <?= bitrix_sessid_post() ?>
    <p><?= Loc::getMessage('MLK_SEARCHAI_GENERATE_DESC') ?></p>
    <label><?= Loc::getMessage('MLK_SEARCHAI_IBLOCK_ID') ?>:
        <input type="text" name="iblock_id" value="2">
    </label>
    <input type="submit" value="<?= Loc::getMessage('MLK_SEARCHAI_START_GENERATION') ?>" class="adm-btn-save">
</form>
<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");