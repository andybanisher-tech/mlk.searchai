<?

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\HttpApplication;

$module_id = 'mlk.searchai';
Loader::includeModule($module_id);

Loc::loadMessages(__FILE__);
$request = HttpApplication::getInstance()->getContext()->getRequest();

$tabs = [
    [
        'DIV' => 'general',
        'TAB' => Loc::getMessage('MLK_SEARCHAI_TAB_GENERAL'),
        'TITLE' => Loc::getMessage('MLK_SEARCHAI_TAB_GENERAL_TITLE')
    ],
    [
        'DIV' => 'llm',
        'TAB' => Loc::getMessage('MLK_SEARCHAI_TAB_LLM'),
        'TITLE' => Loc::getMessage('MLK_SEARCHAI_TAB_LLM_TITLE')
    ]
];

$arAllOptions = [
    'general' => [
    ['iblock_id', Loc::getMessage('MLK_SEARCHAI_IBLOCK_ID'), '2', ['text', 10]],
    ['search_fields', Loc::getMessage('MLK_SEARCHAI_SEARCH_FIELDS'), 'NAME,CODE,PROPERTY_ARTICLE', ['text', 50]],
    ['results_limit', Loc::getMessage('MLK_SEARCHAI_RESULTS_LIMIT'), '5', ['text', 5]],
    ['enable_suggestions', Loc::getMessage('MLK_SEARCHAI_ENABLE_SUGGESTIONS'), 'Y', ['checkbox']],
    ['filter_active', Loc::getMessage('MLK_SEARCHAI_FILTER_ACTIVE'), 'Y', ['checkbox']],
    ['filter_available', Loc::getMessage('MLK_SEARCHAI_FILTER_AVAILABLE'), 'Y', ['checkbox']],
    ['filter_price_not_empty', Loc::getMessage('MLK_SEARCHAI_FILTER_PRICE'), 'Y', ['checkbox']],
    ['filter_quantity_not_zero', Loc::getMessage('MLK_SEARCHAI_FILTER_QUANTITY'), 'N', ['checkbox']],
    ],
    'llm' => [
        ['llm_enable', Loc::getMessage('MLK_SEARCHAI_LLM_ENABLE'), 'Y', ['checkbox']],
        ['llm_provider', Loc::getMessage('MLK_SEARCHAI_LLM_PROVIDER'), 'mistral', ['select', [
            'mistral' => 'Mistral AI (бесплатно)',
            'groq' => 'Groq (быстрый)',
            'custom' => 'Свой сервер (OpenAI-совместимый)'
        ]]],
        ['llm_api_key', Loc::getMessage('MLK_SEARCHAI_LLM_API_KEY'), '', ['text', 50]],
        ['llm_model', Loc::getMessage('MLK_SEARCHAI_LLM_MODEL'), 'mistral-small', ['text', 30]],
        ['llm_base_url', Loc::getMessage('MLK_SEARCHAI_LLM_BASE_URL'), '', ['text', 50]]
    ]
];

// Сохранение настроек
if ($request->isPost() && check_bitrix_sessid()) {
    foreach ($arAllOptions as $tabOptions) {
        foreach ($tabOptions as $option) {
            $name = $option[0];
            $value = $request->getPost($name);
            Option::set($module_id, $name, is_array($value) ? implode(',', $value) : $value);
        }
    }
}

$tabControl = new CAdminTabControl('tabControl', $tabs);
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($module_id) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <?
    $tabControl->Begin();
    foreach ($arAllOptions as $tabName => $options) {
        $tabControl->BeginNextTab();
        foreach ($options as $option) {
            $name = $option[0];
            $title = $option[1];
            $default = $option[2];
            $type = $option[3];
            $value = Option::get($module_id, $name, $default);
    ?>
            <tr>
                <td width="40%"><?= $title ?></td>
                <td width="60%">
                    <? if ($type[0] == 'text'): ?>
                        <input type="text" name="<?= $name ?>" value="<?= htmlspecialcharsbx($value) ?>" size="<?= $type[1] ?>">
                    <? elseif ($type[0] == 'checkbox'): ?>
                        <input type="checkbox" name="<?= $name ?>" value="Y" <?= $value == 'Y' ? 'checked' : '' ?>>
                    <? elseif ($type[0] == 'select'): ?>
                        <select name="<?= $name ?>">
                            <? foreach ($type[1] as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $value == $key ? 'selected' : '' ?>><?= $label ?></option>
                            <? endforeach ?>
                        </select>
                    <? elseif ($type[0] == 'textarea'): ?>
                        <textarea name="<?= $name ?>" rows="<?= $type[1] ?>" cols="<?= $type[2] ?>"><?= htmlspecialcharsbx($value) ?></textarea>
                    <? endif ?>
                </td>
            </tr>
    <?
        }
    }
    $tabControl->Buttons();
    ?>
    <input type="submit" name="save" value="<?= Loc::getMessage('MLK_SEARCHAI_SAVE') ?>" class="adm-btn-save">
    <? $tabControl->End(); ?>
</form>