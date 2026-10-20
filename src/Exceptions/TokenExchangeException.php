<?php

namespace Bps\Sso\Exceptions;

class TokenExchangeException extends BpsSsoException
{
    protected ?array $responseBody;

    public function __construct(string $message, int $code = 0, ?array $responseBody = null)
    {
        parent::__construct($message, $code);
        $this->responseBody = $responseBody;
    }

    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }
}
