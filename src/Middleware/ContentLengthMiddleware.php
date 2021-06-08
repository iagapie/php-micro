<?php

declare(strict_types=1);

namespace IA\Micro\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ContentLengthMiddleware implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $size = $response->getBody()->getSize();

        if (null !== $size && !$response->hasHeader('Content-Length')) {
            $response = $response->withHeader('Content-Length', (string)$size);
        }

        return $response;
    }
}