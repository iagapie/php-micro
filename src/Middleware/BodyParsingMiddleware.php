<?php

declare(strict_types=1);

namespace IA\Micro\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function count;
use function explode;
use function is_array;
use function is_null;
use function is_object;
use function is_string;
use function json_decode;
use function parse_str;
use function strtolower;
use function trim;

final class BodyParsingMiddleware implements MiddlewareInterface
{
    /**
     * @var callable[]
     */
    private array $bodyParsers = [];

    /**
     * BodyParsingMiddleware constructor.
     * @param array $bodyParsers
     */
    public function __construct(array $bodyParsers = [])
    {
        $this->registerDefaultBodyParsers();

        foreach ($bodyParsers as $mediaType => $parser) {
            $this->registerBodyParser($mediaType, $parser);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();

        if (null === $parsedBody || empty($parsedBody)) {
            $parsedBody = $this->parseBody($request);
            $request = $request->withParsedBody($parsedBody);
        }

        return $handler->handle($request);
    }

    /**
     * @param string $mediaType A HTTP media type (excluding content-type params).
     * @param callable $callable A callable that returns parsed contents for media type.
     */
    protected function registerBodyParser(string $mediaType, callable $callable): void
    {
        $this->bodyParsers[$mediaType] = $callable;
    }

    protected function registerDefaultBodyParsers(): void
    {
        $this->registerBodyParser(
            'application/json',
            static function ($input) {
                $result = json_decode($input, true);

                if (!is_array($result)) {
                    return null;
                }

                return $result;
            }
        );

        $this->registerBodyParser(
            'application/x-www-form-urlencoded',
            static function ($input) {
                parse_str($input, $data);
                return $data;
            }
        );

        $xmlCallable = static function ($input) {
            $backup_errors = \libxml_use_internal_errors(true);
            $result = \simplexml_load_string($input);

            \libxml_clear_errors();
            \libxml_use_internal_errors($backup_errors);

            if (false === $result) {
                return null;
            }

            return $result;
        };

        $this->registerBodyParser('application/xml', $xmlCallable);
        $this->registerBodyParser('text/xml', $xmlCallable);
    }

    /**
     * @param ServerRequestInterface $request
     * @return array|object|null
     */
    protected function parseBody(ServerRequestInterface $request): array|object|null
    {
        $mediaType = $this->getMediaType($request);

        if (null === $mediaType) {
            return null;
        }

        // Check if this specific media type has a parser registered first
        if (!isset($this->bodyParsers[$mediaType])) {
            // If not, look for a media type with a structured syntax suffix (RFC 6839)
            $parts = explode('+', $mediaType);
            if (count($parts) >= 2) {
                $mediaType = 'application/'.$parts[count($parts) - 1];
            }
        }

        if (isset($this->bodyParsers[$mediaType])) {
            $body = (string)$request->getBody();
            $parsed = $this->bodyParsers[$mediaType]($body);

            if (!is_null($parsed) && !is_object($parsed) && !is_array($parsed)) {
                throw new \RuntimeException(
                    'Request body media type parser return value must be an array, an object, or null'
                );
            }

            return $parsed;
        }

        return null;
    }

    /**
     * @param ServerRequestInterface $request
     * @return string|null The serverRequest media type, minus content-type params
     */
    protected function getMediaType(ServerRequestInterface $request): ?string
    {
        $contentType = $request->getHeader('Content-Type')[0] ?? null;

        if (is_string($contentType) && trim($contentType) !== '') {
            $contentTypeParts = explode(';', $contentType);

            return strtolower(trim($contentTypeParts[0]));
        }

        return null;
    }
}