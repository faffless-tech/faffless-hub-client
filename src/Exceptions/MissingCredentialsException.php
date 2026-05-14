<?php

namespace Faffless\HubClient\Exceptions;

class MissingCredentialsException extends FafflessHubException
{
    public static function make(): self
    {
        return new self('Faffless Hub app id and secret must be configured before making API requests.');
    }
}
