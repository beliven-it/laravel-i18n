<?php

namespace Beliven\I18n;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Beliven\I18n\Commands\I18nCommand;

class I18nServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name("laravel-i18n")->hasCommand(I18nCommand::class);
    }
}
