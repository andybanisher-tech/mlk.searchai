<?
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\HttpApplication;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\UserFieldTable;

$module_id = 'mlk.searchai';
Loader::includeModule($module_id);

Loc::loadMessages(__FILE__);
$request = HttpApplication::getInstance()->getContext()->getRequest();

// --- Пользовательские поля пользователя ---
$userFieldsList = [];
$rsUserFields = UserFieldTable::getList([
    'order' => ['FIELD_NAME' => 'ASC'],
    'filter' => ['=ENTITY_ID' => 'USER'],
    'select' => ['FIELD_NAME']
]);
while ($uf = $rsUserFields->fetch()) {
    $fieldName = $uf['FIELD_NAME'];
    $userFieldsList[$fieldName] = $fieldName;
}

// --- Свойства выбранного инфоблока ---
$iblockId = (int)($request->getPost('iblock_id') ?? Option::get($module_id, 'iblock_id', 2));
$productPropsList = [];
$productFieldsList = []; // Для AI-источников
if ($iblockId > 0 && Loader::includeModule('iblock')) {
    $rsProps = PropertyTable::getList([
        'order' => ['SORT' => 'ASC', 'NAME' => 'ASC'],
        'filter' => ['=IBLOCK_ID' => $iblockId],
        'select' => ['CODE', 'NAME']
    ]);
    while ($prop = $rsProps->fetch()) {
        if (!empty($prop['CODE'])) {
            $productPropsList[$prop['CODE']] = '[' . $prop['CODE'] . '] ' . $prop['NAME'];
            $productFieldsList['PROPERTY_' . $prop['CODE']] = 'Свойство: ' . $prop['NAME'];
        }
    }
    // Стандартные поля элемента
    $standardFields = [
        'NAME' => 'Название',
        'DETAIL_TEXT' => 'Детальное описание',
        'PREVIEW_TEXT' => 'Анонс',
        'TAGS' => 'Теги',
    ];
    foreach ($standardFields as $code => $name) {
        $productFieldsList[$code] = 'Поле: ' . $name;
    }
}

// --- Вкладки ---
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
    ],
    [
        'DIV' => 'aisearch',
        'TAB' => Loc::getMessage('MLK_SEARCHAI_TAB_AISEARCH'),
        'TITLE' => Loc::getMessage('MLK_SEARCHAI_TAB_AISEARCH_TITLE')
    ],
];

// --- Набор опций по вкладкам ---
$arAllOptions = [
    'general' => [
        ['iblock_id', Loc::getMessage('MLK_SEARCHAI_IBLOCK_ID'), '2', ['text', 10]],
        ['search_fields', Loc::getMessage('MLK_SEARCHAI_SEARCH_FIELDS'), 'NAME,CODE,PROPERTY_ARTICLE', ['text', 50]],
        ['results_limit', Loc::getMessage('MLK_SEARCHAI_RESULTS_LIMIT'), '5', ['text', 5]],
        ['enable_suggestions', Loc::getMessage('MLK_SEARCHAI_ENABLE_SUGGESTIONS'), 'Y', ['checkbox']],
        ['filter_active', Loc::getMessage('MLK_SEARCHAI_FILTER_ACTIVE'), 'Y', ['checkbox']],
        ['filter_use_catalog', Loc::getMessage('MLK_SEARCHAI_FILTER_USE_CATALOG'), 'N', ['checkbox']],
        ['filter_available', Loc::getMessage('MLK_SEARCHAI_FILTER_AVAILABLE'), 'Y', ['checkbox']],
        ['filter_price_not_empty', Loc::getMessage('MLK_SEARCHAI_FILTER_PRICE'), 'Y', ['checkbox']],
        ['filter_quantity_not_zero', Loc::getMessage('MLK_SEARCHAI_FILTER_QUANTITY'), 'N', ['checkbox']],
    ],
    'llm' => [
        ['llm_enable', Loc::getMessage('MLK_SEARCHAI_LLM_ENABLE'), 'Y', ['checkbox']],
        ['llm_context_enable', Loc::getMessage('MLK_SEARCHAI_LLM_CONTEXT_ENABLE'), 'Y', ['checkbox']],
        ['llm_provider', Loc::getMessage('MLK_SEARCHAI_LLM_PROVIDER'), 'mistral', ['select', [
            'mistral' => 'Mistral AI (бесплатно)',
            'groq' => 'Groq (быстрый)',
            'custom' => 'Свой сервер (OpenAI-совместимый)'
        ]]],
        ['llm_api_key', Loc::getMessage('MLK_SEARCHAI_LLM_API_KEY'), '', ['text', 50]],
        ['llm_model', Loc::getMessage('MLK_SEARCHAI_LLM_MODEL'), 'mistral-small', ['text', 30]],
        ['llm_base_url', Loc::getMessage('MLK_SEARCHAI_LLM_BASE_URL'), '', ['text', 50]]
    ],
    'aisearch' => [
        ['ai_feature_enabled', Loc::getMessage('MLK_SEARCHAI_AI_FEATURE_ENABLED'), 'N', ['checkbox']],
        ['ai_source_fields', Loc::getMessage('MLK_SEARCHAI_AI_SOURCE_FIELDS'), ['NAME', 'DETAIL_TEXT'], ['multiselect', $productFieldsList]],
        ['ai_model', Loc::getMessage('MLK_SEARCHAI_AI_MODEL'), 'mistral-large', ['text', 30]],
        ['ai_prompt_template', Loc::getMessage('MLK_SEARCHAI_AI_PROMPT_TEMPLATE'), 'Проанализируй запрос пользователя. Твоя задача - переформулировать его в поисковый запрос, удалив лишние слова и оставив только ключевые термины, описывающие товар.', ['textarea', 5, 60]],
    ],
];

// Добавляем поля персонализации в общую вкладку
$arAllOptions['general'][] = ['user_field_code', Loc::getMessage('MLK_SEARCHAI_USER_FIELD_CODE'), '', ['select', array_merge(['' => '-- не выбрано --'], $userFieldsList)]];

// --- Сохранение ---
if ($request->isPost() && check_bitrix_sessid()) {
    foreach ($arAllOptions as $tabOptions) {
        foreach ($tabOptions as $option) {
            $name = $option[0];
            $type = $option[3][0];
            $value = $request->getPost($name);
            if ($type === 'checkbox') {
                $value = $value === 'Y' ? 'Y' : 'N';
            } elseif ($type === 'multiselect') {
                $value = is_array($value) ? implode(',', $value) : '';
            }
            Option::set($module_id, $name, $value ?? '');
        }
    }
    // Дополнительные настройки персонализации
    Option::set($module_id, 'product_entity_type', $request->getPost('product_entity_type') ?: 'SECTION');
    Option::set($module_id, 'product_field_type', $request->getPost('product_field_type') ?: 'PROPERTY');
    Option::set($module_id, 'product_field_code', $request->getPost('product_field_code') ?: '');
}

$tabControl = new CAdminTabControl('tabControl', $tabs);
?>

<script>
function updateProductFieldSelect() {
    var iblockId = BX('iblock_id').value;
    var entityType = BX('product_entity_type').value;
    var fieldType = BX('product_field_type').value;
    var select = BX('product_field_code');
    
    select.innerHTML = '<option value="">-- выберите --</option>';
    
    if (!iblockId || !entityType || !fieldType) return;
    
    BX.ajax.post(
        '/bitrix/admin/mlk_searchai_field_ajax.php',
        {
            iblock_id: iblockId,
            entity_type: entityType
        },
        function(data) {
            if (data) {
                data = JSON.parse(data);
                if (fieldType === 'STANDARD') {
                    for (var code in data.standard_fields) {
                        select.options[select.options.length] = new Option(data.standard_fields[code], code);
                    }
                } else {
                    data.properties.forEach(function(prop) {
                        select.options[select.options.length] = new Option(prop.name, prop.code);
                    });
                }
                <? if (!empty(Option::get($module_id, 'product_field_code', ''))): ?>
                BX('product_field_code').value = '<?=CUtil::JSEscape(Option::get($module_id, 'product_field_code', ''))?>';
                <? endif; ?>
            }
        }
    );
}

BX.ready(function() {
    if (BX('iblock_id')) {
        BX('iblock_id').addEventListener('change', updateProductFieldSelect);
        BX('product_entity_type').addEventListener('change', updateProductFieldSelect);
        BX('product_field_type').addEventListener('change', updateProductFieldSelect);
        updateProductFieldSelect();
    }
});
</script>

<form method="post" action="<?=$APPLICATION->GetCurPage()?>?mid=<?=htmlspecialcharsbx($module_id)?>&lang=<?=LANGUAGE_ID?>">
    <?=bitrix_sessid_post()?>
    <?
    $tabControl->Begin();
    foreach ($arAllOptions as $tabName => $options) {
        $tabControl->BeginNextTab();
        foreach ($options as $option) {
            $name = $option[0];
            $title = $option[1];
            $default = $option[2];
            $type = $option[3];
            $value = Option::get($module_id, $name, is_array($default) ? implode(',', $default) : $default);
            ?>
            <tr>
                <td width="40%"><?=$title?></td>
                <td width="60%">
                    <?if ($type[0] == 'text'):?>
                        <input type="text" name="<?=$name?>" id="<?=$name?>" value="<?=htmlspecialcharsbx($value)?>" size="<?=$type[1]?>">
                    <?elseif ($type[0] == 'checkbox'):?>
                        <input type="hidden" name="<?=$name?>" value="N">
                        <input type="checkbox" name="<?=$name?>" id="<?=$name?>" value="Y" <?=$value == 'Y' ? 'checked' : ''?>>
                    <?elseif ($type[0] == 'select'):?>
                        <select name="<?=$name?>" id="<?=$name?>">
                            <?foreach ($type[1] as $key => $label):?>
                                <option value="<?=$key?>" <?=$value == $key ? 'selected' : ''?>><?=$label?></option>
                            <?endforeach?>
                        </select>
                    <?elseif ($type[0] == 'multiselect'):?>
                        <select name="<?=$name?>[]" id="<?=$name?>" multiple size="5">
                            <?foreach ($type[1] as $key => $label):?>
                                <?$values = explode(',', $value);?>
                                <option value="<?=$key?>" <?=in_array($key, $values) ? 'selected' : ''?>><?=$label?></option>
                            <?endforeach?>
                        </select>
                    <?elseif ($type[0] == 'textarea'):?>
                        <textarea name="<?=$name?>" rows="<?=$type[1]?>" cols="<?=$type[2]?>"><?=htmlspecialcharsbx($value)?></textarea>
                    <?endif?>
                </td>
            </tr>
            <?
        }
        // Блок персонализации в общей вкладке
        if ($tabName === 'general'):
        ?>
        <tr>
            <td colspan="2"><b><?= Loc::getMessage('MLK_SEARCHAI_PERSONALIZATION_SETTINGS') ?></b></td>
        </tr>
        <tr>
            <td><?= Loc::getMessage('MLK_SEARCHAI_PRODUCT_ENTITY_TYPE') ?>:</td>
            <td>
                <select name="product_entity_type" id="product_entity_type">
                    <option value="SECTION" <?= Option::get($module_id, 'product_entity_type', 'SECTION') == 'SECTION' ? 'selected' : '' ?>>Раздел</option>
                    <option value="ELEMENT" <?= Option::get($module_id, 'product_entity_type', 'SECTION') == 'ELEMENT' ? 'selected' : '' ?>>Элемент</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><?= Loc::getMessage('MLK_SEARCHAI_PRODUCT_FIELD_TYPE') ?>:</td>
            <td>
                <select name="product_field_type" id="product_field_type">
                    <option value="PROPERTY" <?= Option::get($module_id, 'product_field_type', 'PROPERTY') == 'PROPERTY' ? 'selected' : '' ?>>Свойство</option>
                    <option value="STANDARD" <?= Option::get($module_id, 'product_field_type', 'PROPERTY') == 'STANDARD' ? 'selected' : '' ?>>Стандартное поле</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><?= Loc::getMessage('MLK_SEARCHAI_PRODUCT_FIELD_CODE') ?>:</td>
            <td>
                <select name="product_field_code" id="product_field_code">
                    <option value="">-- выберите --</option>
                </select>
            </td>
        </tr>
        <?endif;
    }
    $tabControl->Buttons();
    ?>
    <input type="submit" name="save" value="<?=Loc::getMessage('MLK_SEARCHAI_SAVE')?>" class="adm-btn-save">
    <input type="submit" name="apply" value="<?=Loc::getMessage('MLK_SEARCHAI_APPLY')?>" class="adm-btn-save">
    <?$tabControl->End();?>
</form>