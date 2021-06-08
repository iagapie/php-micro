<?php

declare(strict_types=1);

namespace IA\Micro\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Throwable;

final class ErrorEvent extends Event
{
    public const NAME = 'middleware.error';

    /**
     * ErrorEvent constructor.
     * @param Throwable $exception
     */
    public function __construct(protected Throwable $exception)
    {
    }

    /**
     * @return Throwable
     */
    public function getException(): Throwable
    {
        return $this->exception;
    }

    /**
     * @param Throwable $exception
     */
    public function setException(Throwable $exception): void
    {
        $this->exception = $exception;
    }
}