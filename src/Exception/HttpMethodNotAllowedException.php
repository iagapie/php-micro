<?php

declare(strict_types=1);

namespace IA\Micro\Exception;

use Throwable;

final class HttpMethodNotAllowedException extends HttpException
{
    /**
     * HttpMethodNotAllowedException constructor.
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
            405,
            ['message' => 'The request method is not supported.'],
            $message,
            $code,
            $previous
        );
    }
}