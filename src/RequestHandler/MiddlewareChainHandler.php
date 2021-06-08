<?php

declare(strict_types=1);

namespace IA\Micro\RequestHandler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Traversable;

use function array_reduce;
use function iterator_to_array;

final class MiddlewareChainHandler implements RequestHandlerInterface
{
    /**
     * MiddlewareChain constructor.
     * @param RequestHandlerInterface $defaultHandler
     * @param iterable<MiddlewareInterface> $middlewares
     */
    public function __construct(private RequestHandlerInterface $defaultHandler, private iterable $middlewares)
    {
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middlewares = $this->middlewares instanceof Traversable
            ? iterator_to_array($this->middlewares)
            : $this->middlewares;

        return array_reduce(
            $middlewares,
            fn($carry, $item) => new MiddlewareHandler($item, $carry),
            $this->defaultHandler
        )->handle($request);
    }
}