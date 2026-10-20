<?php

namespace Bps\Sso\Events;

use Illuminate\Queue\SerializesModels;

class BpsSsoAuthenticated
{
    use SerializesModels;

    /**
     * The authenticated user model instance.
     *
     * @var mixed
     */
    public $user;

    /**
     * User profile information from Keycloak /userinfo endpoint.
     *
     * @var array
     */
    public array $userInfo;

    /**
     * Tokens returned from Keycloak /token endpoint.
     * (access_token, id_token, refresh_token, expires_in)
     *
     * @var array
     */
    public array $tokens;

    /**
     * Create a new event instance.
     */
    public function __construct($user, array $userInfo, array $tokens)
    {
        $this->user = $user;
        $this->userInfo = $userInfo;
        $this->tokens = $tokens;
    }
}
