<?php

use Bitrix\Im\StatusTable;
use Bitrix\Intranet\UserTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main\UI\PageNavigation;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

class EoaLievBitrix24UsersList extends \CBitrixComponent
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
        // Массив для сообщений об ошибке
        $this->arResult['ERROR_MESSAGES'] = [];

        // Инициализируем параметры грида
        $this->arResult['GRID_PARAMS'] = $this->getGridParams();

        $this->IncludeComponentTemplate();
    }

    // Возвращает параметры грида
    protected function getGridParams(): array
    {
        $params = [
            'GRID_ID' => $this->getGridId(),
            'COLUMNS' => $this->getGridColumns(),
            'SHOW_ROW_CHECKBOXES' => false,
            'AJAX_MODE' => 'Y',
            'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
            'SHOW_PAGESIZE' => true,
            'PAGE_SIZES' => [
                ['NAME' => '10', 'VALUE' => '10'],
                ['NAME' => '30', 'VALUE' => '30'],
                ['NAME' => '50', 'VALUE' => '50'],
                ['NAME' => '100', 'VALUE' => '100'],
                ['NAME' => 'Все', 'VALUE' => (string) $this->getRecordCount()],
            ],
            'AJAX_OPTION_JUMP' => 'N',
            'SHOW_CHECK_ALL_CHECKBOXES' => false,
            'SHOW_SELECTED_COUNTER' => false,
            'SHOW_ROW_ACTIONS_MENU' => true,
            'SHOW_GRID_SETTINGS_MENU' => true,
            'SHOW_ACTION_PANEL' => false,
            'ACTION_PANEL' => false,
            'ALLOW_COLUMNS_SORT' => true,
            'ALLOW_COLUMNS_RESIZE' => true,
            'ALLOW_HORIZONTAL_SCROLL' => true,
            'ALLOW_SORT' => true,
            'ALLOW_PIN_HEADER' => true,
            'AJAX_OPTION_HISTORY' => 'N',
            'SHOW_NAVIGATION_PANEL' => true,
            'SHOW_PAGINATION' => true,
            'SHOW_TOTAL_COUNTER' => true,
            'TOTAL_ROWS_COUNT' => $this->getRecordCount(),
        ];

        // Постраничная навигация
        $params['NAV_OBJECT'] = $this->getGridNavigation($params['GRID_ID']);

        // Строки грида
        $params['ROWS'] = $this->getGridRows(
            $params['GRID_ID'],
            $params['NAV_OBJECT']
        );

        return $params;
    }

    // Возвращает идентификатор грида
    protected function getGridId(): string
    {
        return strtoupper(static::class).'_V1';
    }

    // Возвращает колонки грида
    protected function getGridColumns(): array
    {
        return [
            [
                'id' => 'ID',
                'name' => 'ID',
                'sort' => 'ID',
                'default' => false,
            ],
            [
                'id' => 'LAST_NAME',
                'name' => 'Фамилия',
                'sort' => 'LAST_NAME',
                'default' => true,
            ],
            [
                'id' => 'NAME',
                'name' => 'Имя',
                'sort' => 'NAME',
                'default' => true,
            ],
            [
                'id' => 'SECOND_NAME',
                'name' => 'Отчество',
                'sort' => 'SECOND_NAME',
                'default' => true,
            ],
            [
                'id' => 'WORK_POSITION',
                'name' => 'Должность',
                'sort' => 'WORK_POSITION',
                'default' => true,
            ],
            [
                'id' => 'WORK_PHONE',
                'name' => 'Рабочий телефон',
                'sort' => 'WORK_PHONE',
                'default' => true,
            ],
            [
                'id' => 'MANAGER',
                'name' => 'Фамилия начальника',
                'sort' => 'MANAGER',
                'default' => true,
            ],
            [
                'id' => 'SUBORDINATES_COUNT',
                'name' => 'Количество подчиненных',
                'sort' => 'SUBORDINATES_COUNT',
                'default' => true,
            ],
            [
                'id' => 'STATUS',
                'name' => 'Статус пользователя',
                'sort' => 'STATUS',
                'default' => true,
            ],
        ];
    }

    // Возвращает объект постраничной навигации
    protected function getGridNavigation(string $gridId): PageNavigation
    {
        $navParams = (new GridOptions($gridId))->GetNavParams();

        $navigation = new PageNavigation($gridId);
        $navigation
            ->setRecordCount(
                $this->getRecordCount()
            )
            ->setPageSize(
                $navParams['nPageSize']
            )
            ->initFromUri();

        return $navigation;
    }

    // Возвращает параметры грида дополненные строками и общим колличеством
    protected function getGridRows(string $gridId, PageNavigation $navigation): array
    {
        if (!Loader::includeModule('intranet')) {
            $this->arResult['ERROR_MESSAGES'][] = 'Для отображения списка пользователей нужно установить модуль "intranet"';
            return [];
        }

        // Сформируем запрос в базу данных
        $rows = $this->getGridRowsQuery($gridId, $navigation)
            ->exec();

        $items = [];
        while ($row = $rows->fetch()) {
            $columns = $this->prepareRowColumns($row);

            $items[] = [
                'data' => $row,
                'columns' => $columns,
                'actions' => [
                    [
                        'text'    => 'Просмотреть профиль',
                        'default' => true,
                        'onclick' => "BX.SidePanel.Instance.open(\"{$columns['DETAIL_PAGE_URL']}\")",
                    ],
                ],
            ];
        }

        return $items;
    }

    // Возвращает объект запроса списка пользователей
    protected function getGridRowsQuery(string $gridId, PageNavigation $navigation): Query
    {
        $query = $this->getGridRowsQueryBase();

        $this->navigationExtendsQuery($query, $navigation);
        $this->statusExtendsQuery($query);
        $this->sortExtendsQuery($query, $gridId);

        return $query;
    }

    // Возвращает базовый объект запроса списка пользователей
    protected function getGridRowsQueryBase(): Query
    {
        return UserTable::query()
            ->setSelect([
                'ID',
                'LAST_NAME',
                'NAME',
                'SECOND_NAME',
                'WORK_POSITION',
                'WORK_PHONE',
                'USER_TYPE',
                'UF_DEPARTMENT',
            ])
            ->where('ACTIVE', 'Y');
    }

    // Расширяет объект запроса списка пользователей ограничениями постраничной навигации
    protected function navigationExtendsQuery(Query $query, PageNavigation $navigation)
    {
        return $query
            ->setOffset(
                $navigation->getOffset()
            )
            ->setLimit(
                $navigation->getLimit()
            );
    }

    // Расширяет объект запроса списка пользователей информацией о статусах
    protected function statusExtendsQuery(Query $query)
    {
        if (!Loader::includeModule('im')) {
            return;
        }

        $query
            ->registerRuntimeField(
                null,
                (new Reference(
                    'STATUS',
                    StatusTable::class,
                    Join::on('this.ID', 'ref.USER_ID')
                ))->configureJoinType('left')
            )
            ->addSelect('STATUS.STATUS', 'STATUS_CODE')
            ->addSelect('STATUS.STATUS_TEXT', 'STATUS_TEXT');
    }

    // Расширяет объект запроса списка пользователей сортировкой
    protected function sortExtendsQuery(Query $query, string $gridId)
    {
        $sort = (new GridOptions($gridId))->GetSorting([
            'sort' => ['ID' => 'ASC'],
            'vars' => ['by' => 'by', 'order' => 'order'],
        ]);

        $query->setOrder($sort['sort']);
    }

    // Возвращает количество уведомлений
    protected function getRecordCount(): int
    {
        if (null !== $this->recordCount) {
            return $this->recordCount;
        }

        return $this->recordCount = $this->getGridRowsQueryBase()
            ->setSelect(['ID'])
            ->setLimit(1)
            ->countTotal(true)
            ->exec()
            ->getCount();
    }

    // Преобразовывет значения полей пользователя для отображения в гриде
    protected function prepareRowColumns(array $row): array
    {
        $columns = $row;

        $columns['DETAIL_PAGE_URL'] = $this->getUserDetailPageUrl(
            (int) $row['ID']
        );

        $columns['STATUS'] = $this->renderColumnStatus(
            (string) $row['STATUS_CODE'],
            (string) $row['STATUS_TEXT']
        );

        $columns['MANAGER'] = '';
        $columns['SUBORDINATES_COUNT'] = '';

        if ('employee' === $row['USER_TYPE']) {
            if (is_array($row['UF_DEPARTMENT'])) {
                $columns['MANAGER'] = $this->renderColumnManager(
                    $row['ID'],
                    $row['UF_DEPARTMENT']
                );
            }

            $columns['SUBORDINATES_COUNT'] = $this->renderColumnSubordinatesCount(
                $row['ID']
            );
        }

        return $columns;
    }

    // Возвращает путь до профиля пользователя
    protected function getUserDetailPageUrl(int $userId): string
    {
        return \CComponentEngine::MakePathFromTemplate(
            $this->arParams['PATH_TO_USER'],
            [
                'ID' => $userId,
                'USER_ID' => $userId,
            ]
        );
    }

    // Возвращает текстовое представление статуса пользователя
    protected function renderColumnStatus(string $code, string $text): string
    {
        $parts = [];

        if (0 < strlen($code)) {
            $parts[] = $code;
        }

        if (0 < strlen($text)) {
            $parts[] = '('.$text.')';
        }

        return implode(' ', $parts);
    }

    protected function renderColumnManager(int $userId, array $departmentsIds): string
    {
        $managers = \CIntranetUtils::GetDepartmentManager(
            $departmentsIds,
            $userId,
            true
        );
        if (0 >= count($managers)) {
            return '';
        }

        foreach ($managers as $manager) {
            return sprintf(
                '<a href="%s" title="%s">%s</a>',
                $this->getUserDetailPageUrl($manager['ID']),
                $manager['LAST_NAME'] ?: $manager['NAME'],
                $manager['LAST_NAME'] ?: $manager['NAME']
            );
        }

        return '';
    }

    protected function renderColumnSubordinatesCount(int $id): string
    {
        $count = \CIntranetUtils::getSubordinateEmployees(
            $id,
            true,
            'Y',
            ['ID']
        )->SelectedRowsCount();

        return 0 < $count ? (string) $count: '';
    }
}
