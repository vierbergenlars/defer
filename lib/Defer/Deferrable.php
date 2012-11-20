<?php

namespace Defer;

/**
 * Base interface for all deferrable classes
 */
interface Deferrable
{
    /**
     * Imports the given data through the loader
     * @param  Loader $loader The loader used for the data
     * @param  mixed  $data   The data to load
     * @return mixed
     */
    public static function import(Loader $loader, $data);
}
