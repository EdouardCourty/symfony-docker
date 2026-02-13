<?php

// User's service configuration file
// This file is loaded into the Symfony DI container

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        // Override default parameters here
        // ->set('mate.cache_dir', sys_get_temp_dir().'/mate')
        // ->set('mate.env_file', ['.env']) // This will load mate/.env and mate/.env.local

        // Symfony bridge configuration
        ->set('ai_mate_symfony.cache_dir', __DIR__.'/../var/cache/dev')
        ->set('ai_mate_symfony.profiler_dir', __DIR__.'/../var/cache/dev/profiler')

        // Monolog bridge configuration
        ->set('ai_mate_monolog.log_dir', __DIR__.'/../var/log')
    ;

    $container->services()
        // Register your custom services here
    ;
};
