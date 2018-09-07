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
    private $increments = [];
    /**
     * @covers ::rewrite
     * @dataProvider influxDbMessages
     */
    public function testRewrite(string $message, array $tags)
    {
        $dd = $this->createMock(DogStatsd::class);
        $dd->method('increment')
            ->willReturnCallback(function (...$args) {
                $this->increments[] = $args;
            });
        $rewriter = new Rewriter($dd);
        $rewriter->rewrite($message);
        var_dump($this->increments);
    }

    public function influxDbMessages(): array
    {
        return [
            [
                'default,server_name=amp.reefpig.com method="GET",status=500,bytes_sent=248,body_bytes_sent=21,header_bytes_sent=227,request_length=833,uri="/questions/2000",extension="",content_type="text/plain; charset=utf-8",request_time=0.047',
                ['code' => '500',
                 'method' => 'GET',
                 'server_name' => 'amp.reefpig.com',
                ],
            ],
        ];
    }
}
