<?php

declare(strict_types=1);

namespace Workbunny\CronParser\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Workbunny\CronParser\Parser;

#[CoversClass(Parser::class)]
class ParserTest extends TestCase
{
    public function testConstructorWithValidCrontabString()
    {
        $parser = new Parser('* * * * * *');
        $this->assertInstanceOf(Parser::class, $parser);
    }

    public function testConstructorWithInvalidCrontabString()
    {
        $this->expectException(InvalidArgumentException::class);
        new Parser('invalid cron string');
    }

    public function testGetLastTimestamp()
    {
        $parser = new Parser('* * * * * *');
        $currentTime = time();
        $lastTimestamp = $parser->getLastTimestamp($currentTime);
        $this->assertIsInt($lastTimestamp);
        $this->assertLessThanOrEqual($currentTime, $lastTimestamp);
    }

    public function testGetNextTimestamp()
    {
        $parser = new Parser('* * * * * *');
        $currentTime = time();
        $nextTimestamp = $parser->getNextTimestamp($currentTime);
        $this->assertIsInt($nextTimestamp);
        $this->assertGreaterThanOrEqual($currentTime, $nextTimestamp);
    }

    public function testGetCrontabString()
    {
        $crontabString = '* * * * * *';
        $parser = new Parser($crontabString);
        self::assertEquals($crontabString, $parser->getCrontabString());
    }

    public function testSetCrontabString()
    {
        $parser = new Parser('* * * * * *');
        $newCrontabString = '*/5 * * * * *';
        $parser->setCrontabString($newCrontabString);
        self::assertEquals($newCrontabString, $parser->getCrontabString());
    }

    public function testGetStartTime()
    {
        $startTime = time();
        $parser = new Parser('* * * * * *', $startTime);
        self::assertEquals($startTime, $parser->getStartTime());
    }

    public function testSetStartTime()
    {
        $parser = new Parser('* * * * * *');
        $newStartTime = time() + 3600;
        $parser->setStartTime($newStartTime);
        self::assertEquals($newStartTime, $parser->getStartTime());
    }

    public function testGetParseDate()
    {
        $parser = new Parser('* * * * * *');
        $parseDate = $parser->getParseDate();
        self::assertIsArray($parseDate);
    }

    public function testSetParseDate()
    {
        $parser = new Parser('* * * * * *');
        $newParseDate = [
            'second' => [0],
            'minutes' => [0],
            'hours' => [0],
            'day' => [1],
            'month' => [1],
            'week' => [0]
        ];
        $parser->setParseDate($newParseDate);
        self::assertEquals($newParseDate, $parser->getParseDate());
    }

    public function testIsValid()
    {
        self::assertTrue(Parser::isValid('* * * * * *'));
        self::assertFalse(Parser::isValid('invalid cron string'));
    }

    public function testGetDate()
    {
        $timestamp = time();
        $date = $this->invokeProtectedMethod('_getDate', [$timestamp]);
        self::assertIsArray($date);
        self::assertArrayHasKey('year', $date);
        self::assertArrayHasKey('month', $date);
        self::assertArrayHasKey('week', $date);
        self::assertArrayHasKey('day', $date);
        self::assertArrayHasKey('hours', $date);
        self::assertArrayHasKey('minutes', $date);
        self::assertArrayHasKey('second', $date);
    }

    public function testParseDate()
    {
        $crontabString = '* * * * * *';
        $parseDate = $this->invokeProtectedMethod('_parseDate', [$crontabString]);
        self::assertIsArray($parseDate);
        self::assertArrayHasKey('second', $parseDate);
        self::assertArrayHasKey('minutes', $parseDate);
        self::assertArrayHasKey('hours', $parseDate);
        self::assertArrayHasKey('day', $parseDate);
        self::assertArrayHasKey('month', $parseDate);
        self::assertArrayHasKey('week', $parseDate);

        $crontabString = '* * * * *';
        $parseDate = $this->invokeProtectedMethod('_parseDate', [$crontabString]);
        self::assertIsArray($parseDate);
        self::assertArrayHasKey('second', $parseDate);
        self::assertArrayHasKey('minutes', $parseDate);
        self::assertArrayHasKey('hours', $parseDate);
        self::assertArrayHasKey('day', $parseDate);
        self::assertArrayHasKey('month', $parseDate);
        self::assertArrayHasKey('week', $parseDate);
        self::assertEquals([1 => 0], $parseDate['second']);
    }

    public function testParseSegment()
    {
        $segment = $this->invokeProtectedMethod('_parseSegment', ['*', 0, 59]);
        self::assertIsArray($segment);
        self::assertCount(60, $segment);

        $segment = $this->invokeProtectedMethod('_parseSegment', ['1,2,,4', 0, 59]);
        self::assertIsArray($segment);
        self::assertEquals([1, 2, 4], $segment);

        $segment = $this->invokeProtectedMethod('_parseSegment', ['1,2,60', 0, 59]);
        self::assertIsArray($segment);
        self::assertEquals([1, 2], $segment);

        $segment = $this->invokeProtectedMethod('_parseSegment', ['1,2,3', 0, 59]);
        self::assertIsArray($segment);
        self::assertEquals([1, 2, 3], $segment);

        $segment = $this->invokeProtectedMethod('_parseSegment', ['0/15', 0, 59]);
        self::assertIsArray($segment);
        self::assertEquals([0, 15, 30, 45], $segment);

        $segment = $this->invokeProtectedMethod('_parseSegment', ['1-5', 0, 59]);
        self::assertIsArray($segment);
        self::assertEquals([1, 2, 3, 4, 5], $segment);

        $segment = $this->invokeProtectedMethod('_parseSegment', ['1-5/2', 0, 59]);
        self::assertIsArray($segment);
        self::assertEquals([1, 3, 5], $segment);

        $segment = $this->invokeProtectedMethod('_parseSegment', ['1-5,7-9', 0, 59]);
        self::assertIsArray($segment);
        self::assertEquals([1, 2, 3, 4, 5, 7, 8, 9], $segment);

        $segment = $this->invokeProtectedMethod('_parseSegment', ['1-5/2,7-9/2', 0, 59]);
        self::assertIsArray($segment);
        self::assertEquals([1, 3, 5, 7, 9], $segment);

        $segment = $this->invokeProtectedMethod('_parseSegment', ['1-5/2,7-9/3', 0, 59]);
        self::assertIsArray($segment);
        self::assertEquals([1, 3, 5, 7], $segment);

        $segment = $this->invokeProtectedMethod('_parseSegment', ['1-5/2,7-9/4', 0, 59]);
        self::assertIsArray($segment);
        self::assertEquals([1, 3, 5, 7], $segment);

        $segment = $this->invokeProtectedMethod('_parseSegment', ['1-5/2,7-9/5', 0, 59]);
        self::assertIsArray($segment);
        self::assertEquals([1, 3, 5, 7], $segment);

        $segment = $this->invokeProtectedMethod('_parseSegment', ['10-15', 0, 59]);
        self::assertIsArray($segment);
        self::assertEquals([10, 11, 12, 13, 14, 15], $segment);

        $segment = $this->invokeProtectedMethod('_parseSegment', ['10', 0, 59]);
        self::assertIsArray($segment);
        self::assertEquals([10], $segment);

        $segment = $this->invokeProtectedMethod('_parseSegment', ['60', 0, 59]);
        self::assertIsArray($segment);
        self::assertEmpty($segment);
    }


    public function testIsBetween()
    {
        self::assertTrue($this->invokeProtectedMethod('_isBetween', [5, 0, 10]));
        self::assertFalse($this->invokeProtectedMethod('_isBetween', [15, 0, 10]));
    }

    public function testGetNextNumber()
    {
        $needle = 5;
        $haystack = [0, 5, 10];
        $result = $this->invokeProtectedMethod('_getNextNumber', [$needle, $haystack]);
        self::assertIsArray($result);
        self::assertFalse($result[0]);
        self::assertEquals(5, $result[1]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('$needle cannot be negative');
        $this->invokeProtectedMethod('_getNextNumber', [-1, $haystack]);
    }

    public function testGetLastNumber()
    {
        $needle = 5;
        $haystack = [0, 5, 10];
        $result = $this->invokeProtectedMethod('_getLastNumber', [$needle, $haystack]);
        self::assertIsArray($result);
        self::assertFalse($result[0]);
        self::assertEquals(5, $result[1]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('$needle cannot be negative');
        $this->invokeProtectedMethod('_getLastNumber', [-1, $haystack]);
    }

    private function invokeProtectedMethod($methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(Parser::class);
        $method = $reflection->getMethod($methodName);

//        $method->setAccessible(true);

        return $method->invokeArgs(null, $parameters);
    }
}

