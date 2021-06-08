<?php

declare(strict_types=1);

namespace IA\Micro\Exception;

use Throwable;

final class HttpTeapotException extends HttpException
{
    /**
     * HttpTeapotException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            418,
            ['message' => 'I\'m a teapot.'],
            $message,
            $code,
            $previous
        );
    }
}