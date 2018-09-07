<?php
declare(strict_types=1);

namespace Slant\Monitoring;

use DataDog\DogStatsd;

/**
 * @coversDefaultClass Slant\Monitoring\Rewriter
 * @covers ::<protected>
 * @covers ::<private>
 */
class RewriterTest extends \PHPUnit\Framework\TestCase
{
    private $expectedIncrements = [];
    private $expectedTimers = [];

    private $timers = [];
    private $increments = [];

    /**
     * @covers ::rewrite
     * @dataProvider influxDbMessages
     */
    public function testRewrite(string $message, string $prefix, float $time, array $tags)
    {
        $dd = $this->createMock(DogStatsd::class);
        $dd->method('microtiming')
            ->willReturnCallback(function (...$args) {
                $this->timers[] = $args;
            });
        $rewriter = new Rewriter($dd);
        $dd->method('increment')
            ->willReturnCallback(function (...$args) {
                $this->increments[] = $args;
            });
        $rewriter = new Rewriter($dd);
        $rewriter->rewrite($message);

        $this->expectIncrement('request.count');
        $this->expectTimer('request.duration', $time);
        $this->performAssertions($prefix, $tags);
    }

    public function influxDbMessages(): array
    {
        return [
            [
                'default,server_name=amp.reefpig.com method="GET",status=500,'.
                'bytes_sent=248,body_bytes_sent=21,header_bytes_sent=227,'.
                'request_length=833,uri="/questions/2000",extension="",'.
                'content_type="text/plain; charset=utf-8",request_time=0.047',

                'default',
                0.047,
                ['code' => '500',
                 'method' => 'GET',
                 'server_name' => 'amp.reefpig.com',
                ],
            ],
        ];
    }

    private function expectIncrement(string $metric)
    {
        $this->expectedIncrements[] = $metric;
    }

    private function expectTimer(string $metric, float $seconds)
    {
        $this->expectedTimers[] = [$metric, $seconds];
    }

    private function performAssertions(string $prefix, array $tags)
    {
        $this->assertCount(count($this->expectedIncrements), $this->increments, 'Incorrect increment count');
        $this->assertCount(count($this->expectedTimers), $this->timers, 'Incorrect timer count');

        foreach ($this->expectedIncrements as $suffix) {
            $metric = sprintf('%s.%s', $prefix, $suffix);
            $found = false;
            foreach ($this->increments as $increment) {
                if ($increment[0] === $metric) {
                    $found = $increment;
                    break;
                }
            }
            $this->assertNotFalse($found, sprintf('Expected increment metric %s not found', $metric));
            ksort($tags);
            ksort($found[2]);
            $this->assertEquals($tags, $found[2], 'Tags do not match');
        }

        foreach ($this->expectedTimers as $et) {
            list($suffix, $seconds) = $et;
            $metric = sprintf('%s.%s', $prefix, $suffix);
            $found = false;
            foreach ($this->timers as $timer) {
                if ($timer[0] === $metric) {
                    $found = $timer;
                    break;
                }
            }
            $this->assertNotFalse($found, sprintf('Timer metric %s not found', $metric));
            $this->assertEquals($seconds, $found[1], 'Incorrect timing value');
            ksort($tags);
            ksort($found[3]);
            $this->assertEquals($tags, $found[3], 'Tags do not match');
        }
    }
}
