<?php

declare(strict_types=1);

namespace IA\Micro\Exception;

use Throwable;

final class HttpBadRequestException extends HttpException
{
    /**
     * HttpBadRequestException constructor.
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
            400,
            ['message' => 'The request is badly performed.'],
            $message,
            $code,
            $previous
        );
    }
}