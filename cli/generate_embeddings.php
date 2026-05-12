<?php
$_SERVER["DOCUMENT_ROOT"] = realpath(__DIR__ . '/../../..');
require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Mlk\Searchai\Search\Embedder;

Loader::includeModule('mlk.searchai');
Loader::includeModule('iblock');

$iblockId = 2; // Или из настроек
$res = \CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'], false, false, ['ID', 'NAME', 'DETAIL_TEXT']);
while ($el = $res->Fetch()) {
    $text = $el['NAME'] . ' ' . ($el['DETAIL_TEXT'] ?? '');
    $emb = Embedder::getEmbedding($text);
    if ($emb) {
        Embedder::saveEmbedding($el['ID'], $emb);
    }
    echo "Processed {$el['ID']}\n";
}