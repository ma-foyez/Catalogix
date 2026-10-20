<?php

namespace App\Exceptions;

use RuntimeException;

class RenderFailedException extends RuntimeException
{
    public static function fromProcess(string $errorOutput, ?int $exitCode): self
    {
        $message = trim($errorOutput) !== '' ? trim($errorOutput) : 'The Python helper exited with code '.($exitCode ?? 'unknown').'.';

        return new self(mb_substr($message, 0, 1000));
    }
}
