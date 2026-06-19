<?php

namespace arifje\loginwithemailcode\models;

use craft\elements\User;

class LoginResult
{
    public User $user;
    public ?string $redirect;

    public function __construct(User $user, ?string $redirect = null)
    {
        $this->user = $user;
        $this->redirect = $redirect;
    }
}
