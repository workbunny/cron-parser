<?php
/**
 * This file is part of workbunny.
 *
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    chaz6chez<chaz6chez1993@outlook.com>
 * @copyright chaz6chez<chaz6chez1993@outlook.com>
 */
declare(strict_types=1);

namespace Workbunny\CronParser;

use function array_key_first;
use function array_merge;
use function arsort;
use function asort;
use function count;
use function explode;

use InvalidArgumentException;

use function preg_split;
use function str_contains;
use function trim;

class Parser
{
    protected string $_crontabString;
    protected int $_startTime;
    protected array $_parseDate = [];

    /**
     * @param string $crontabString
     *
     *   0   1   2   3   4   5
     *   |   |   |   |   |   |
     *   |   |   |   |   |   +-- week(0-6,Sunday=0)
     *   |   |   |   |   +--- month(1-12)
     *   |   |   |   +---- day(1-31)
     *   |   |   +----- hour(0-23)
     *   |   +------ min(0-59)
     *   +------- sec(0-59) [optional]
     *
     *  Supported special symbols: * , - /
     *  Unsupported special symbols: L W
     *
     * @param int|null $startTime
     */
    public function __construct(string $crontabString, ?int $startTime = null)
    {
        if (!static::isValid($crontabString)) {
            throw new InvalidArgumentException('Invalid cron string: ' . $crontabString);
        }
        $this->setStartTime($startTime ?? time());
        $this->setCrontabString($crontabString);
        $this->setParseDate(static::_parseDate($crontabString));
    }

    /**
     * 获取上一次执行时间
     *  注：上次执行时间 >= 当前时间
     *
     * @param int|null $currentTime
     * @return int
     */
    public function getLastTimestamp(?int $currentTime = null): int
    {
        $currentTime = $currentTime ?? time();
        $parseDate = $this->getParseDate();
        $currentDate = static::_getDate($currentTime);
        // 获取近似值
        list($lastMin, $second) = static::_getLastNumber($currentDate['second'], $parseDate['second']);
        list($lastHour, $minute) = static::_getLastNumber($currentDate['minutes'], $parseDate['minutes'], (int) $lastMin);
        list($lastDay, $hour) = static::_getLastNumber($currentDate['hours'], $parseDate['hours'], (int) $lastHour);
        list($lastMonth, $day) = static::_getLastNumber($currentDate['day'], $parseDate['day'], (int) $lastDay);
        list($lastYear, $month) = static::_getLastNumber($currentDate['month'], $parseDate['month'], (int) $lastMonth);
        // 获取下次时间戳
        $year = $currentDate['year'] - (int) $lastYear;
        $timestamp = strtotime("$year-$month-$day $hour:$minute:$second");
        // 判断星期
        list($last, $week) = static::_getNextNumber($currentDate['week'], $parseDate['week']);

        return !$last ? $timestamp : $timestamp - ((7 - $week + $currentDate['week']) * 86400);
    }

    /**
     * 获取下一次执行时间
     *  注：当前时间与下次时间是可以相等的
     *
     * @param int|null $currentTime
     * @return int
     */
    public function getNextTimestamp(?int $currentTime = null): int
    {
        $currentTime = $currentTime ?? time();
        $parseDate = $this->getParseDate();
        $currentDate = static::_getDate($currentTime);
        // 获取近似值
        list($nextMin, $second) = static::_getNextNumber($currentDate['second'], $parseDate['second']);
        list($nextHour, $minute) = static::_getNextNumber($currentDate['minutes'], $parseDate['minutes'], (int) $nextMin);
        list($nextDay, $hour) = static::_getNextNumber($currentDate['hours'], $parseDate['hours'], (int) $nextHour);
        list($nextMonth, $day) = static::_getNextNumber($currentDate['day'], $parseDate['day'], (int) $nextDay);
        list($nextYear, $month) = static::_getNextNumber($currentDate['month'], $parseDate['month'], (int) $nextMonth);
        // 获取下次时间戳
        $year = $currentDate['year'] + (int) $nextYear;
        $timestamp = strtotime("$year-$month-$day $hour:$minute:$second");
        // 判断星期
        list($next, $week) = static::_getNextNumber($currentDate['week'], $parseDate['week']);

        return !$next ? $timestamp : (7 - $currentDate['week'] + $week) * 86400 + $timestamp;
    }

    /**
     * @return string
     */
    public function getCrontabString(): string
    {
        return $this->_crontabString;
    }

    /**
     * @param string $crontabString
     */
    public function setCrontabString(string $crontabString): void
    {
        $this->_crontabString = $crontabString;
    }

    /**
     * @return int
     */
    public function getStartTime(): int
    {
        return $this->_startTime;
    }

    /**
     * @param int $startTime
     */
    public function setStartTime(int $startTime): void
    {
        $this->_startTime = $startTime;
    }

    /**
     * @return array
     */
    public function getParseDate(): array
    {
        return $this->_parseDate;
    }

    /**
     * @param array $parseDate
     */
    public function setParseDate(array $parseDate): void
    {
        $this->_parseDate = $parseDate;
    }

    /**
     * @param string $crontabString
     * @return bool
     */
    public static function isValid(string $crontabString): bool
    {
        if (!preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i', trim($crontabString))) {
            if (!preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i', trim($crontabString))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param int $timestamp
     * @return array
     */
    protected static function _getDate(int $timestamp): array
    {
        return [
            'year'    => (int) date('Y', $timestamp),
            'month'   => (int) date('n', $timestamp),
            'week'    => (int) date('w', $timestamp),
            'day'     => (int) date('j', $timestamp),
            'hours'   => (int) date('G', $timestamp),
            'minutes' => (int) date('i', $timestamp),
            'second'  => (int) date('s', $timestamp),
        ];
    }

    /**
     * @param string $crontabString
     * @return array|array[]
     */
    protected static function _parseDate(string $crontabString): array
    {
        $cron = preg_split('/[\\s]+/i', trim($crontabString));
        if (count($cron) === 6) {
            $date = [
                'second'  => static::_parseSegment($cron[0], 0, 59),
                'minutes' => static::_parseSegment($cron[1], 0, 59),
                'hours'   => static::_parseSegment($cron[2], 0, 23),
                'day'     => static::_parseSegment($cron[3], 1, 31),
                'month'   => static::_parseSegment($cron[4], 1, 12),
                'week'    => static::_parseSegment($cron[5], 0, 6),
            ];
        } else {
            $date = [
                'second'  => [1 => 0],
                'minutes' => static::_parseSegment($cron[0], 0, 59),
                'hours'   => static::_parseSegment($cron[1], 0, 23),
                'day'     => static::_parseSegment($cron[2], 1, 31),
                'month'   => static::_parseSegment($cron[3], 1, 12),
                'week'    => static::_parseSegment($cron[4], 0, 6),
            ];
        }

        return $date;
    }

    /**
     * Parse each segment of crontab string.
     * @param string $string
     * @param int $min
     * @param int $max
     * @param int|null $start
     * @return array
     */
    protected static function _parseSegment(string $string, int $min, int $max, ?int $start = null): array
    {
        if ($start === null or $start < $min) {
            $start = $min;
        }
        $result = [];
        if ($string === '*') {
            for ($i = $start; $i <= $max; ++$i) {
                $result[] = $i;
            }
        } elseif (str_contains($string, ',')) {
            $exploded = explode(',', $string);
            foreach ($exploded as $value) {
                if (str_contains($value, '/') || str_contains($string, '-')) {
                    $result = array_merge($result, static::_parseSegment($value, $min, $max, $start));
                    continue;
                }
                if (trim($value) === '' || !static::_isBetween((int) $value, (int) ($min > $start ? $min : $start), (int) $max)) {
                    continue;
                }
                $result[] = (int) $value;
            }
        } elseif (str_contains($string, '/')) {
            $exploded = explode('/', $string);
            if (str_contains($exploded[0], '-')) {
                [$nMin, $nMax] = explode('-', $exploded[0]);
                $nMin > $min and $min = (int) $nMin;
                $nMax < $max and $max = (int) $nMax;
            }
            $start < $min and $start = $min;
            for ($i = $start; $i <= $max;) {
                $result[] = $i;
                $i += (int) $exploded[1];
            }
        } elseif (str_contains($string, '-')) {
            $result = array_merge($result, static::_parseSegment($string . '/1', $min, $max, $start));
        } elseif (static::_isBetween((int) $string, $min > $start ? $min : $start, $max)) {
            $result[] = (int) $string;
        }

        return $result;
    }

    /**
     * @param int $value
     * @param int $min
     * @param int $max
     * @return bool
     */
    protected static function _isBetween(int $value, int $min, int $max): bool
    {
        return $value >= $min and $value <= $max;
    }

    /**
     * 获取下一个数
     * @param int $needle
     * @param array $haystack
     * @param int $nextLevel
     * @return array = [
     *               bool, @desc 如果是下一个周期，则为true
     *               int,  @desc 最近的下一个数，包含相等数
     *               ]
     */
    protected static function _getNextNumber(int $needle, array $haystack, int $nextLevel = 0): array
    {
        if ($needle < 0) {
            throw new InvalidArgumentException('$needle cannot be negative');
        }
        $positives = [];
        foreach ($haystack as $key => $value) {
            $value = $value - $needle;
            if ($value >= 0) {
                $positives[$key] = $value;
            }
        }
        asort($positives);
        $key = array_key_first($positives);

        return [
            !isset($haystack[$nextLevel === 0 ? $key : $key + $nextLevel]),
            $haystack[$nextLevel === 0 ? $key : $key + $nextLevel] ?? array_shift($haystack),
        ];
    }

    /**
     * 获取上一个数
     * @param int $needle
     * @param array $haystack
     * @param int $lastLevel
     * @return array = [
     *               bool, @desc 如果是上一个周期，则为true
     *               int,  @desc 上一个数，包含相等数
     *               ]
     */
    protected static function _getLastNumber(int $needle, array $haystack, int $lastLevel = 0): array
    {
        if ($needle < 0) {
            throw new InvalidArgumentException('$needle cannot be negative');
        }
        $negatives = [];
        foreach ($haystack as $key => $value) {
            $value = $value - $needle;
            if ($value <= 0) {
                $negatives[$key] = $value;
            }
        }
        arsort($negatives);
        $key = array_key_first($negatives);

        return [
            !isset($haystack[$lastLevel === 0 ? $key : $key + $lastLevel]),
            $haystack[$lastLevel === 0 ? $key : $key + $lastLevel] ?? array_shift($haystack),
        ];
    }
}
