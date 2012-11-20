<?php

namespace Defer;

/**
 * Interface for an object loader
 */
interface Loader
{
    /**
     * Loads the object
     * @param mixed $identifier An unique identifier for the object
     */
    public function load($identifier);
}
