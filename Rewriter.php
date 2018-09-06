<?php
declare(strict_types=1);

use Datadog\DogStatsd;

class Rewriter
{
    private const STATE_TAG_NAME = 1;
    private const STATE_TAG_VALUE = 2;

    /** @var DogStatsd */
    private $datadog;

    public function __construct(DogStatsd $datadog)
    {
        $this->datadog = $datadog;
    }

    public function rewrite(string $influxDbMessage)
    {
        list($head, $tagStr) = explode(' ', $influxDbMessage, 2);
        list($measurement, $serverNameTagStr) = explode(',', $head, 2);
        list($_, $serverName) = explode('=', $serverNameTagStr, 2);
        $tags = $this->parseTags($tagStr);
        // var_dump($measurement, $serverName, $tags);

        $this->datadog->increment($measurement.'.request.count', 1.0, [
            'code' => $tags['status'],
            'method' => $tags['method'],
            'server_name' => $serverName,
        ]);
    }

    private function parseTags(string $tagStr): array
    {
        $tagStr = trim($tagStr);
        $tags = [];
        $tagLen = strlen($tagStr);
        $state = self::STATE_TAG_NAME;

        $tagName = $tagValue = '';
        $quoted = false;
        $escaped = false;

        // This is doing string parsing rather than a simple explode in case
        // a comma shows up inside a quoted value
        for ($i = 0; $i < $tagLen; $i++) {
            $char = $tagStr[$i];
            switch ($state) {
            case self::STATE_TAG_NAME:
                if ($char === '=') {
                    $state = self::STATE_TAG_VALUE;
                } elseif ($char === ',') {
                    // tag with no value
                    $tags[$tagName] = $tagValue;
                    $tagName = $tagValue = '';
                } else {
                    $tagName .= $char;
                }
                break;
            case self::STATE_TAG_VALUE:
                if ($char === ',' && !$quoted) {
                    $state = self::STATE_TAG_NAME;
                    $tags[$tagName] = $tagValue;
                    $tagName = $tagValue = '';
                } elseif ($char === '"' && $quoted && !$escaped) {
                    // End of quoted value
                    $quoted = false;
                } elseif ($char === '"' && !$quoted) {
                    $quoted = true;
                } elseif ($char === '\\' && $quoted && !$escaped) {
                    $escaped = true;
                } else {
                    $escaped = false;
                    $tagValue .= $char;
                }
                break;
            default:
                throw new \Exception("Invalid state $state");
            }
        }
        // Insert last remaining value, which is not caught by last character
        $tags[$tagName] = $tagValue;
        return $tags;
    }
}

