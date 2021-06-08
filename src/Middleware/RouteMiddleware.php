<?php

declare(strict_types=1);

namespace IA\Micro\Middleware;

use IA\Micro\Exception\HttpException;
use IA\Micro\Exception\HttpMethodNotAllowedException;
use IA\Micro\Exception\HttpNotFoundException;
use IA\Route\Exception\RouteException;
use IA\Route\Resolver\Result;
use IA\Route\Resolver\RouteResolverInterface;
use IA\Route\RouteInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

use function implode;

final class RouteMiddleware implements MiddlewareInterface
{
    /**
     * RouteMiddleware constructor.
     * @param RouteResolverInterface $routeResolver
     * @param LoggerInterface|null $logger
     */
    public function __construct(private RouteResolverInterface $routeResolver, private ?LoggerInterface $logger = null)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Result $result */
        $result = $request->getAttribute(Result::class);

        if (null !== $this->logger) {
            $this->logger->debug(
                'RouteMiddleware - result {status} for {uri}',
                ['status' => $result->getStatus(), 'uri' => $request->getUri()->getPath()]
            );
        }

        switch ($result->getStatus()) {
            case Result::NOT_FOUND:
                throw new HttpNotFoundException();
            case Result::METHOD_NOT_ALLOWED:
                $message = sprintf(
                    'Allowed methods %s: %s',
                    $result->getIdentifier() ?? '-',
                    implode(', ', $result->getAllowedMethods())
                );
                throw new HttpMethodNotAllowedException(message: $message);
        }

        try {
            $route = $this->routeResolver->resolve($result->getIdentifier());
        } catch (RouteException $e) {
            throw new HttpException(previous: $e);
        }

        $request = $request->withAttribute(RouteInterface::class, $route);

        return $handler->handle($request);
    }
}