<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

trait HasUnixTimestamps
{
    /**
     * 返回 Unix 时间戳格式
     */
    public function getDateFormat(): string
    {
        return 'U';
    }

    /**
     * 序列化日期为 Unix 时间戳
     */
    protected function serializeDate(DateTimeInterface $date): int
    {
        return $date->getTimestamp();
    }
}
