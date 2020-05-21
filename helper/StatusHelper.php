<?php

namespace EoaLievBitrix24UsersList\Helper;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\Datetime;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

class StatusHelper
{
    const TIMEMAN_STATUS_EXPIRED = 'EXPIRED';
    const TIMEMAN_STATUS_OPENED = 'OPENED';
    const TIMEMAN_STATUS_PAUSED = 'PAUSED';
    const TIMEMAN_STATUS_CLOSED = 'CLOSED';

    protected static $absenceData = null;

    protected $userId = 0;
    protected $timemanStatus = null;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    protected function getAbsences(): ?array
    {
        if (!Loader::includeModule('intranet')) {
            return static::$absenceData = [];
        }

        if (null === static::$absenceData) {
            static::$absenceData = \CIntranetUtils::GetAbsenceData([
                'DATE_START' => new Datetime(),
                'DATE_FINISH' => new Datetime(),
                'SELECT' => ['PROPERTY_ABSENCE_TYPE', 'PROPERTY_USER']
            ]);
        }

        return static::$absenceData[$this->userId] ?: null;
    }

    public function getStatus(): ?string
    {
        // Получим статус учета рабочего дня
        $timemanStatus = $this->getTimemanStatus();

        // Если день начат, то не важно имеется ли отсутствие
        if (static::TIMEMAN_STATUS_OPENED === $timemanStatus) {
            return $this->getTimemanStatusTitle($timemanStatus);
        }

        // Если есть отсутствие вернем его
        if ($absenceType = $this->getAbsenceType()) {
            return "Отсутствует ({$absenceType})";
        }

        // Вернем статус учета рабочего дня
        return $this->getTimemanStatusTitle($timemanStatus);
    }

    protected function getTimemanStatus(): string
    {
        if (null !== $this->timemanStatus) {
            return $this->timemanStatus;
        }

        if (!Loader::includeModule('timeman')) {
            return static::TIMEMAN_STATUS_CLOSED;
        }

        $timemanUser = new \CTimemanUser($this->userId);

        return $this->timemanStatus = $timemanUser->State() ?: static::TIMEMAN_STATUS_CLOSED;
    }

    protected function getAbsenceType(): ?string
    {
        if (!($abseces = $this->getAbsences())) {
            return null;
        }

        $absence = reset($abseces);
        unset($abseces);

        return $absence['PROPERTY_ABSENCE_TYPE_VALUE'];
    }

    protected function getTimemanStatusTitles(): array
    {
        return [
            static::TIMEMAN_STATUS_EXPIRED => 'Забыл завершить день',
            static::TIMEMAN_STATUS_OPENED => 'Работает',
            static::TIMEMAN_STATUS_PAUSED => 'Перерыв',
            static::TIMEMAN_STATUS_CLOSED => 'Не работает',
        ];
    }

    protected function getTimemanStatusTitle(string $status): ?string
    {
        $titles = $this->getTimemanStatusTitles();
        return $titles[$status] ?: null;
    }
}
