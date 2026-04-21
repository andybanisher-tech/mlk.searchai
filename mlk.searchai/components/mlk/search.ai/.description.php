<?php
if (!defined("B_PROLOG_ADDED") || !defined("LOCAL_PATH")) {
    die("Access Denied");
}

$arComponentDescription = [
    'NAME' => GetMessage('MLK_SEARCHAI_COMPONENT_NAME'),
    'DESCRIPTION' => GetMessage('MLK_SEARCHAI_COMPONENT_DESCRIPTION'),
    'ICON' => '/images/searchai_icon.gif',
    'PATH' => [
        'ID' => 'mlk',
        'NAME' => 'Your Partner Code'
    ],
    'KEYWORDS' => 'поиск, AI, поиск с искусственным интеллектом',
    'BASELINE' => true,
    'AREA_BUTTONS' => [
        [
            'URL' => 'javascript:CBXFeature.EditFrame();',
            'TEXT' => GetMessage('MLK_SEARCHAI_COMPONENT_EDIT_FRAME')
        ]
    ]
];
