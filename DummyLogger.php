<?php

namespace dokuwiki\plugin\statistics;

/**
 * A logger that does nothing
 *
 * Used when logging is not wanted or possible
 */
class DummyLogger
{
    /**
     * Ignore whatever is called
     *
     * @param string $name ignored
     * @param array $arguments ignored
     * @return null
     */
    public function __call($name, $arguments)
    {
        return null;
    }
}
