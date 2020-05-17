<?php

use Bitrix\Main\Config\Option;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

class AlieveoBitrix24UsersList extends \CBitrixComponent
{
    public function onPrepareComponentParams($params)
    {
        if (!is_array($params)) {
            $params = [];
        }

        if (!isset($params['PATH_TO_USER']) || 0 >= strlen($params['PATH_TO_USER'])) {
            $params['PATH_TO_USER'] = Option::get(
                'intranet',
                'search_user_url',
                '/company/personal/user/#USER_ID#/'
            );
        }

        return parent::onPrepareComponentParams($params);
    }

    public function executeComponent()
    {
        $this->IncludeComponentTemplate();
    }
}
