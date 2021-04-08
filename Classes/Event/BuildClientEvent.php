<?php

declare(strict_types=1);

namespace SFC\Staticfilecache\Event;

use AUS\AusProject\Middleware\ProgressBodyMiddleware;
use GuzzleHttp\HandlerStack;

class BuildClientEvent
{
    protected array $options;

    protected array $httpOptions;

    public function __construct(array $options, array $httpOptions)
    {
        $this->options = $options;
        $this->httpOptions = $httpOptions;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getHttpOptions(): array
    {
        //return $this->httpOptions;
        // TODO if this works, we have to use the eventdispatcher to get our middleware into account
        $httpOptions = $this->httpOptions;
        $stack = HandlerStack::create();
        $stack->push(function (callable $handler) {
            return new ProgressBodyMiddleware($handler);
        } , 'progress_body');
        $httpOptions['handler'] = $stack;
        return $httpOptions;
    }

    public function setHttpOptions(array $httpOptions): void
    {
        $this->httpOptions = $httpOptions;
    }
}
