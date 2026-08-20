<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Auth\GoogleOAuthException;

final class FileGetContentsHttpTransport implements HttpTransport
{
    /** @var callable(string, bool, resource): (string|false) */
    private $fetcher;

    /**
     * @param callable(string, bool, resource): (string|false)|null $fetcher
     */
    public function __construct(?callable $fetcher = null)
    {
        $this->fetcher = $fetcher ?? 'file_get_contents';
    }

    /**
     * @param array<string, string> $headers
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): string
    {
        $headerBlob = '';
        foreach ($headers as $name => $value) {
            $headerBlob .= $name . ': ' . $value . "\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headerBlob,
                'content' => $body ?? '',
                'ignore_errors' => true,
                'timeout' => 15,
            ],
        ]);

        $result = ($this->fetcher)($url, false, $context);
        if ($result === false) {
            throw new GoogleOAuthException('Unable to reach Google.');
        }

        return $result;
    }
}
