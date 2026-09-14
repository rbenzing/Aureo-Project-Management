<?php

declare(strict_types=1);

namespace App\Core;

/**
 * An HTTP response as an immutable value.
 *
 * Every other method on this class is pure, which is the entire point: the
 * response an action or a middleware decided on can be asserted as data.
 * send() is the single place that touches PHP's output layer, and it is kept
 * deliberately small because it is the one method no test can cover.
 *
 * send() must never call exit. Router::dispatch() is the last statement inside
 * the front controller's try block, so returning normally is enough.
 */
final class HttpResponse
{
    private const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /** @param array<string, string> $headers */
    private function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly string $body,
    ) {
    }

    /**
     * Caching policy and JSON encoding flags are per-caller concerns, not this
     * value object's: Response and ApiResponse have always disagreed on both
     * (Response adds no-cache headers and drops JSON_THROW_ON_ERROR; ApiResponse
     * does the opposite), so json() only sets Content-Type and takes the flags
     * from its caller instead of picking a single policy for everyone.
     */
    public static function json(
        array|object|null $data,
        int $status = 200,
        int $flags = self::DEFAULT_JSON_FLAGS,
    ): self {
        return new self(
            $status,
            ['Content-Type' => 'application/json'],
            (string) json_encode($data, $flags),
        );
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/plain'], $text);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html'], $html);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self($status, ['Location' => $url], '');
    }

    public static function noContent(): self
    {
        return new self(204, [], '');
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->status, $headers, $this->body);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($this->body !== '') {
            echo $this->body;
        }
    }
}
