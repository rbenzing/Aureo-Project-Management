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
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /** @param array<string, string> $headers */
    private function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly string $body,
    ) {
    }

    public static function json(array|object|null $data, int $status = 200): self
    {
        return new self(
            $status,
            [
                'Content-Type' => 'application/json',
                // Matches the headers Response::json() has always sent.
                'Cache-Control' => 'no-cache, must-revalidate',
                'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            ],
            (string) json_encode($data, self::JSON_FLAGS),
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
