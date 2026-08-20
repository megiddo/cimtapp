<?php

declare(strict_types=1);

namespace App\Domain\Crypto;

use RuntimeException;

/**
 * Crypto failures never include key material in the message.
 */
final class CryptoException extends RuntimeException
{
}
