<?php

namespace EoaLievBitrix24UsersList\Helper;

use Bitrix\Intranet\UserTable;
use Bitrix\Main\Loader;
class IntranetHelper
{
    protected $managerIds = [];
    protected $managers = null;

    public function setManagerIds(array $ids): self
    {
        $this->managerIds = $ids;
        $this->managers = null;

        return $this;
    }

    protected function getManagers(): array
    {
        if (null !== $this->managers) {
            return $this->managers;
        }

        if (0 >= count($this->managerIds)) {
            return $this->managers = [];
        }

        $rows = UserTable::query()
            ->setSelect([
                'ID',
                'LAST_NAME',
                'NAME',
            ])
            ->whereIn('ID', $this->managerIds)
            ->setLimit(count($this->managerIds))
            ->exec();

        $items = [];
        while ($row = $rows->fetch()) {
            $items[$row['ID']] = $row;
        }

        return $this->managers = $items;
    }

    public function getManagerIdByUser(array $user): ?int
    {
        if ('employee' !== $user['USER_TYPE'] && !is_array($user['UF_DEPARTMENT'])) {
            return null;
        }

        return $this->getManagerId(
            (int) $user['ID'],
            $user['UF_DEPARTMENT']
        );
    }

    // Возвращает идентификатор руководителя пользователя
    protected function getManagerId(int $userId, array $departmentsIds): ?int
    {
        // Переберем отделы в которые входит пользователь и посмотрим их руководителей
        // Вернем первого найденного
        foreach ($departmentsIds as $departmentId) {
            $managerId = $this->getDepartmentManagerId($departmentId, $userId);
            if (null !== $managerId) {
                return $managerId;
            }
        }

        return null;
    }

    // Возвращает идентификатор руководителя отдела
    protected function getDepartmentManagerId(int $departmentId, int $ignoreUserId): ?int
    {
        if (!Loader::includeModule('intranet')) {
            return null;
        }

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

        return null;
    }

    protected function getManagerById(int $managerId): ?array
    {
        $managers = $this->getManagers();

        return $managers[$managerId] ?: null;
    }

    public function getManagerByUser(array $user): ?array
    {
        if (0 >= (int) $user['MANAGER_ID']) {
            return null;
        }

        return $this->getManagerById(
            (int) $user['MANAGER_ID']
        );
    }

    // Возвращает количество подчиненных пользователя
    public function getSubordinatesCount(int $userId): ?int
    {
        if (!Loader::includeModule('intranet')) {
            return null;
        }

        // Получим все вышестояшие отделы
        $departmentsIds = \CIntranetUtils::GetSubordinateDepartments($userId, true);
        if (0 >= count($departmentsIds)) {
            return null;
        }

        // Получим количество для первого отдела
        // Количество считается рекурсивно
        // Визуально количество считается правильно,
        // но если это не так можно получить структуру \CIntranetUtils::GetStructure()
        // и посчитать ручками
        $count = \CIntranetUtils::GetEmployeesCountForSorting(
            $departmentsIds[0]
        );

        return (int) $count ?: 0;
    }
}
