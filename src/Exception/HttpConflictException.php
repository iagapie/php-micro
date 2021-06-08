<?php

declare(strict_types=1);

namespace IA\Micro\Exception;

use Throwable;

final class HttpConflictException extends HttpException
{
    /**
     * HttpConflictException constructor.
     * @param string $conflict
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        string $conflict,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            409,
            ['message' => $conflict],
            $message,
            $code,
            $previous
        );
    }
}