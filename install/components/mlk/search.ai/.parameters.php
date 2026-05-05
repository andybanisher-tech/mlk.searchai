<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arComponentParameters = array(
    "PARAMETERS" => array(
        "IBLOCK_ID" => array(
            "PARENT" => "BASE",
            "NAME" => "ID инфоблока",
            "TYPE" => "STRING",
            "DEFAULT" => "2",
        ),
        "SEARCH_FIELDS" => array(
            "PARENT" => "BASE",
            "NAME" => "Поля для поиска (через запятую)",
            "TYPE" => "STRING",
            "DEFAULT" => "NAME,CODE,PROPERTY_ARTICLE",
        ),
        "RESULTS_LIMIT" => array(
            "PARENT" => "BASE",
            "NAME" => "Количество результатов",
            "TYPE" => "STRING",
            "DEFAULT" => "5",
        ),
        "SHOW_IMAGES" => array(
            "PARENT" => "VISUAL",
            "NAME" => "Показывать изображения товаров",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ),
        "IMAGE_WIDTH" => array(
            "PARENT" => "VISUAL",
            "NAME" => "Ширина изображения (px)",
            "TYPE" => "STRING",
            "DEFAULT" => "40",
        ),
        "IMAGE_HEIGHT" => array(
            "PARENT" => "VISUAL",
            "NAME" => "Высота изображения (px)",
            "TYPE" => "STRING",
            "DEFAULT" => "40",
        ),
        "CACHE_TIME" => array("DEFAULT" => 3600),
    ),
);