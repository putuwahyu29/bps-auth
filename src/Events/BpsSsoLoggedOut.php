<?php

namespace Bps\Sso\Events;

use Illuminate\Queue\SerializesModels;

class BpsSsoLoggedOut
{
    use SerializesModels;

    /**
     * The user who logged out (if available).
     *
     * @var mixed
     */
    public $user;

    /**
     * Create a new event instance.
     */
    public function __construct($user = null)
    {
        $this->user = $user;
    }
}
