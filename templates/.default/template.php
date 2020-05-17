<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$APPLICATION->IncludeComponent(
    'bitrix:main.ui.grid',
    '',
    $arResult['GRID_PARAMS'],
    $component
);
