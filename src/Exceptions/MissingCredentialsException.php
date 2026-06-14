<?php

namespace Faffless\HubClient\Exceptions;

class MissingCredentialsException extends FafflessHubException
{
    public static function make(): self
    {
        return new self('A Faffless Hub API key must be configured before making API requests.');
    }
}
