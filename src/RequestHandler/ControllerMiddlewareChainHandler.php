<?php

declare(strict_types=1);

namespace IA\Micro\RequestHandler;

use IA\Route\RouteInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_map;

final class ControllerMiddlewareChainHandler implements RequestHandlerInterface
{
    /**
     * DefaultHandler constructor.
     * @param ContainerInterface $container
     */
    public function __construct(private ContainerInterface $container)
    {
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteInterface $route */
        $route = $request->getAttribute(RouteInterface::class);

        $chain = new MiddlewareChainHandler(
            new ControllerHandler($this->container),
            array_map(fn($item) => $this->container->get($item), $route->getMiddlewares())
        );

        return $chain->handle($request);
    }
}