<?php

declare(strict_types=1);

namespace IA\Micro\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function strtoupper;

/**
 * This is to be in compliance with RFC 2616, Section 9.
 * https://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4
 */
final class Rfc2616Sec94Middleware implements MiddlewareInterface
{
    /**
     * Rfc2616Sec94Middleware constructor.
     * @param StreamFactoryInterface $streamFactory
     */
    public function __construct(private StreamFactoryInterface $streamFactory)
    {
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if ('HEAD' === strtoupper($request->getMethod())) {
            $response = $response->withBody($this->streamFactory->createStream());
        }

        return $response;
    }
}