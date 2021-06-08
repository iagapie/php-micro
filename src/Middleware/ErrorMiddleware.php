<?php

declare(strict_types=1);

namespace IA\Micro\Middleware;

use IA\Micro\Event\ErrorEvent;
use IA\Micro\Exception\HttpException;
use IA\Micro\Exception\HttpTeapotException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function json_encode;

final class ErrorMiddleware implements MiddlewareInterface
{
    /**
     * ErrorMiddleware constructor.
     * @param ResponseFactoryInterface $responseFactory
     * @param StreamFactoryInterface $streamFactory
     * @param EventDispatcherInterface $eventDispatcher
     * @param bool $debug
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private EventDispatcherInterface $eventDispatcher,
        private bool $debug,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * @param Throwable $exception
     * @return ResponseInterface
     */
    private function handleException(Throwable $exception): ResponseInterface
    {
        if (null !== $this->logger) {
            $this->logger->error('Error Middleware handle exception', ['exception' => $exception]);
        }

        /** @var ErrorEvent $event */
        $event = $this->eventDispatcher->dispatch(new ErrorEvent($exception), ErrorEvent::NAME);

        $exception = $event->getException();

        if (!$exception instanceof HttpException) {
            $exception = new HttpTeapotException(previous: $exception);
        }

        $data = $exception->getData();

        if ($this->debug) {
            if ($message = $exception->getMessage() ?: $exception->getPrevious()?->getMessage()) {
                $data['developer_message'] = $message;
            }
            $data['trace'] = $exception->getTraceAsString();
        }

        if ($exception->getCode()) {
            $data['code'] = $exception->getCode();
        }

        $body = $this->streamFactory->createStream(json_encode($data));

        return $this->responseFactory->createResponse($exception->getStatusCode())->withBody($body);
    }
}