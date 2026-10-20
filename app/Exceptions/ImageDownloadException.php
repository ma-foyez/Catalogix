<?php

namespace App\Exceptions;

use RuntimeException;

class ImageDownloadException extends RuntimeException
{
    /**
     * @param  bool  $retryable  network trouble and 5xx responses are worth retrying; bad content is not
     */
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }

    public static function retryable(string $message): self
    {
        return new self($message, true);
    }

    public static function permanent(string $message): self
    {
        return new self($message, false);
    }
}
