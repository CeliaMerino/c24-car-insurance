<?php

declare(strict_types=1);

namespace App\Simulator\Behaviour;

/**
 * The values accepted by `PARTNER_{ID}_FORCE`, from specs/04-providers.md
 * section 5.2. A forced behaviour pins the next outcome and bypasses every
 * probability, including the latency draw.
 */
enum ForcedBehaviour: string
{
    case Ok = 'ok';
    case Slow = 'slow';
    case Timeout = 'timeout';
    case HttpError = 'http_error';
    case Malformed = 'malformed';
    case NonJson = 'non_json';
    case ConnectionError = 'connection_error';
}
