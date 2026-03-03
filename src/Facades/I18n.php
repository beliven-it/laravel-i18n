<?php

namespace Beliven\I18n\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Beliven\I18n\I18n
 */
class I18n extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Beliven\I18n\I18n::class;
    }
}
