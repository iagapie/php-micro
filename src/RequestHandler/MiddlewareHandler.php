<?php

declare(strict_types=1);

namespace IA\Micro\RequestHandler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewareHandler implements RequestHandlerInterface
{
    /**
     * MiddlewareHandler constructor.
     * @param MiddlewareInterface $middleware
     * @param RequestHandlerInterface $handler
     */
    public function __construct(private MiddlewareInterface $middleware, private RequestHandlerInterface $handler)
    {
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->middleware->process($request, $this->handler);
    }
}