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
        ];

        // Получим колличество элементов на странице
        // Это делается отдельным запросом так как
        // иначе не корректно строится постраничная навигация
        $params['TOTAL_ROWS_COUNT'] = $this->getRecordCount();

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

        // Сначала переберем пользователей и найдем идентификаторы их менеджеров
        $items = [];
        $managersIds = [];
        while ($row = $rows->fetch()) {
            $row['MANAGER_ID'] = 0;
            if ('employee' === $row['USER_TYPE'] && is_array($row['UF_DEPARTMENT'])) {
                $row['MANAGER_ID'] = $this->getUserManagerId(
                    (int) $row['ID'],
                    $row['UF_DEPARTMENT']
                );
            }

            if (0 < $row['MANAGER_ID']) {
                $managersIds[] = $row['MANAGER_ID'];
            }

            $items[] = [
                'data' => $row,
                'columns' => [],
                'actions' => [],
            ];
        }

        // Если найдены идентификаторы менеджеров
        // сформируем запрос на получение фамилии и имени
        $managers = [];
        if (0 < count($managersIds)) {
            $managers = $this->fetchManagersByIds($managersIds);
        }

        // Подготовим данные для вывода и добавим действие просмотр профиля
        foreach ($items as &$item) {
            $item['columns'] = $this->prepareRowColumns($item['data'], $managers);
            $item['actions'][] = [
                'text'    => 'Просмотреть профиль',
                'default' => true,
                'onclick' => "BX.SidePanel.Instance.open(\"{$item['columns']['DETAIL_PAGE_URL']}\")",
            ];
        }
        unset($item);

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
    protected function prepareRowColumns(array $row, array $managers = []): array
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

        // Если это сотрудник выведем информацию о руководителе и количестве подчененных
        if ('employee' === $row['USER_TYPE']) {
            if (0 < $row['MANAGER_ID'] && isset($managers[$row['MANAGER_ID']])) {
                $columns['MANAGER'] = $this->renderColumnManager(
                    $managers[$row['MANAGER_ID']]
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

    // Возвращает текствовое представление фамилии начальника пользователя
    protected function renderColumnManager(array $manager): string
    {
        return sprintf(
            '<a href="%s" title="%s">%s</a>',
            $this->getUserDetailPageUrl($manager['ID']),
            $manager['LAST_NAME'] ?: $manager['NAME'],
            $manager['LAST_NAME'] ?: $manager['NAME']
        );
    }

    // Возвращает текствовое представление количества подчиненных пользователя
    protected function renderColumnSubordinatesCount(int $id): string
    {
        // Получим все вышестояшие отделы
        $departmentsIds = \CIntranetUtils::GetSubordinateDepartments($id, true);
        if (0 >= count($departmentsIds)) {
            return '';
        }

        // Получим количество для первого отдела
        // Количество считается рекурсивно
        // Визуально количество считается правильно,
        // но если это не так можно получить структуру \CIntranetUtils::GetStructure()
        // и посчитать ручками
        $count = \CIntranetUtils::GetEmployeesCountForSorting(
            $departmentsIds[0]
        );

        return 0 < $count ? (string) $count: '';
    }

    // Возвращает идентификатор руководителя пользователя
    protected function getUserManagerId(int $userId, array $departmentsIds): int
    {
        // Переберем отделы в которые входит пользователь и посмотрим их руководителей
        // Вернем первого найденного
        foreach ($departmentsIds as $departmentId) {
            $managerId = $this->getDepartmentManagerId($departmentId, $userId);
            if (0 < $managerId) {
                return $managerId;
            }
        }

        return 0;
    }

    // Возвращает идентификатор руководителя отдела
    protected function getDepartmentManagerId(int $departmentId, int $ignoreUserId): int
    {
        $structure = \CIntranetUtils::GetStructure();

        // Попробуем сначала руководителя переданного отдела
        $managerId = (int) $structure['DATA'][$departmentId]['UF_HEAD'];
        if (0 < $managerId && $managerId !== $ignoreUserId) {
            return $managerId;
        }

        // Посмотрим вышестоящие отделы
        $parentDepartmentId = (int) $structure['DATA'][$departmentId]['IBLOCK_SECTION_ID'];
        if (0 < $parentDepartmentId) {
            return $this->getDepartmentManagerId($parentDepartmentId, $ignoreUserId);
        }

        return 0;
    }

    // Возвращает аттрибуты менеджеров
    protected function fetchManagersByIds(array $ids): array
    {
        if (0 >= count($ids)) {
            return [];
        }

        $rows = UserTable::query()
            ->setSelect([
                'ID',
                'LAST_NAME',
                'NAME',
            ])
            ->whereIn('ID', $ids)
            ->setLimit(count($ids))
            ->exec();

        $items = [];
        while ($row = $rows->fetch()) {
            $items[$row['ID']] = $row;
        }

        return $items;
    }
}
