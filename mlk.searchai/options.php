<?
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

Loc::loadMessages(__FILE__);
$module_id = 'mlk.searchai';
Loader::includeModule($module_id);

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
        ['results_limit', Loc::getMessage('MLK_SEARCHAI_RESULTS_LIMIT'), '5', ['text', 5]]
    ],
    'llm' => [
        ['llm_provider', Loc::getMessage('MLK_SEARCHAI_LLM_PROVIDER'), 'groq', ['select', [
            'groq' => 'Groq (бесплатно, быстрый)',
            'mistral' => 'Mistral AI (бесплатно)',
            'openrouter' => 'OpenRouter (бесплатно)',
            'custom' => 'Свой сервер'
        ]]],
        ['llm_api_key', Loc::getMessage('MLK_SEARCHAI_LLM_API_KEY'), '', ['text', 50]],
        ['llm_model', Loc::getMessage('MLK_SEARCHAI_LLM_MODEL'), 'llama-3.3-70b-versatile', ['text', 30]],
        ['llm_enable', Loc::getMessage('MLK_SEARCHAI_LLM_ENABLE'), 'Y', ['checkbox']]
    ]
];