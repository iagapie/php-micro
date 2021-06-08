<?php

declare(strict_types=1);

namespace IA\Micro\Exception;

use Throwable;

final class HttpNotFoundException extends HttpException
{
    /**
     * HttpNotFoundException constructor.
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
            404,
            ['message' => 'The requested resource could not be found. Please verify the URI and try again.'],
            $message,
            $code,
            $previous
        );
    }
}