<?php

declare(strict_types=1);

use App\Simulator\Config\EnvVars;
use App\Simulator\Http\SimulatorController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The partner simulator's endpoints (specs/05-api-contract.md section 4). They
 * are internal and must never be reachable from the SPA, so `/sim` is absent
 * from the router altogether in the API container rather than present and
 * refused.
 *
 * A route condition would express this more directly but needs
 * symfony/expression-language, which is a dependency for one boolean. The
 * variable is fixed for the lifetime of a container, so reading it while the
 * routes are loaded costs nothing.
 */
return static function (RoutingConfigurator $routes): void {
    if ('1' !== EnvVars::get('APP_SIMULATOR_ENABLED')) {
        return;
    }

    $routes
        ->add('simulator_partner_quote', '/sim/partners/{partner}/quote')
        ->controller([SimulatorController::class, 'quote'])
        ->methods(['POST']);
};
