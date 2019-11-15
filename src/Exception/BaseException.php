<?php

namespace Solcre\SolcreFramework2\Exception;

class BaseException extends \Exception
{
    protected $additional = [];

    public function __construct($message = '', $code = 0, $additional = [])
    {
        $this->additional = $additional;
        parent::__construct($message, $code);
    }

    /**
     * @return array
     */
    public function getAdditional(): array
    {
        return $this->additional;
    }

    public static function classNameNotFoundException(): self
    {
        return new self('class name was not found', 400);
    }
}