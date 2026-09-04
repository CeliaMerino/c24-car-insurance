<?php

declare(strict_types=1);

namespace App\Simulator\Behaviour;

/**
 * The shape a partner's response takes. Each value corresponds to one of the
 * bodies in specs/04-providers.md section 4.1, except `Success`, which is
 * section 2.2, and `ConnectionError`, which never becomes a response at all.
 */
enum ResponseOutcome: string
{
    case Success = 'success';
    case HttpError = 'http_error';
    case Malformed = 'malformed';
    case NonJson = 'non_json';
    case ConnectionError = 'connection_error';
}
