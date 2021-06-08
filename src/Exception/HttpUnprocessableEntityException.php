<?php

declare(strict_types=1);

namespace IA\Micro\Exception;

use Throwable;

use function array_merge;

final class HttpUnprocessableEntityException extends HttpException
{
    /**
     * HttpTeapotException constructor.
     * @param array $errors
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        private array $errors,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            422,
            ['message' => 'Unprocessable entity.'],
            $message,
            $code,
            $previous
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getData(): array
    {
        return array_merge(parent::getData(), ['errors' => $this->errors]);
    }
}