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
    public function testRewrite(string $message, string $prefix, array $tags)
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
            if (!$found) {
                $this->fail(sprintf('Expected increment metric %s not found', $metric));
            }
            ksort($tags);
            ksort($found[2]);
            $this->assertEquals($tags, $found[2], 'Tags do not match');
        }
    }
}
