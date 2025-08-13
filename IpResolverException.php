<?php

namespace dokuwiki\plugin\statistics;

class IpResolverException extends \Exception
{
    public $details;

    public function __construct($message, $details = null, $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->details = $details;
    }
}
