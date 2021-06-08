<?php

declare(strict_types=1);

namespace IA\Micro\Exception;

use RuntimeException;
use Throwable;

class HttpException extends RuntimeException
{
    /**
     * HttpException constructor.
     * @param int $statusCode
     * @param array $data
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        protected int $statusCode = 500,
        protected array $data = ['message' => 'Internal Server error :('],
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}