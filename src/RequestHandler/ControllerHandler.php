<?php

declare(strict_types=1);

namespace IA\Micro\RequestHandler;

use IA\Micro\Exception\HttpNotFoundException;
use IA\Route\Resolver\Result;
use IA\Route\RouteInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;

use function array_key_exists;
use function explode;
use function is_a;

final class ControllerHandler implements RequestHandlerInterface
{
    /**
     * ControllerHandler constructor.
     * @param ContainerInterface $container
     */
    public function __construct(private ContainerInterface $container)
    {
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws ReflectionException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteInterface $route */
        $route = $request->getAttribute(RouteInterface::class);

        [$serviceId, $method] = explode('::', $route->getHandler());

        if (!$this->container->has($serviceId)) {
            throw new HttpNotFoundException();
        }

        $reflectionMethod = new ReflectionMethod($serviceId, $method);

        /** @var Result $result */
        $result = $request->getAttribute(Result::class);

        $routeArgs = $result->getArguments();
        $arguments = [];

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $name = $parameter->getName();

            $refs[$name] = $parameter;

            if (array_key_exists($name, $routeArgs)) {
                $arguments[$name] = $routeArgs[$name];
                continue;
            }

            if ($parameter->hasType() && $parameter->getType() instanceof ReflectionNamedType) {
                $typeName = $parameter->getType()->getName();

                if (is_a($typeName, ServerRequestInterface::class, true)) {
                    $arguments[$name] = $request;
                    continue;
                }

                if ($this->container->has($typeName)) {
                    $arguments[$name] = $this->container->get($typeName);
                    continue;
                }
            }

            $arguments[$name] = null;
        }

        $service = $this->container->get($serviceId);

        return $reflectionMethod->invokeArgs($service, $arguments);
    }
}