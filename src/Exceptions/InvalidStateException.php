<?php

namespace Bps\Sso\Exceptions;

class InvalidStateException extends BpsSsoException
{
    public function __construct(string $message = 'OAuth state tidak valid atau sudah kadaluarsa. Silakan coba login kembali.', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
