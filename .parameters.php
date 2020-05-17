<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    'GROUPS' => [],
    "PARAMETERS" => [
        'PATH_TO_USER' => [
            'PARENT' => 'BASE',
            'NAME' => 'Путь к профилю пользователя',
            'TYPE' => 'STRING',
            'DEFAULT' => '/company/personal/user/#USER_ID#/',
        ],
    ],
];
