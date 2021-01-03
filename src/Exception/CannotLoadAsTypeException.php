<?php

namespace MeesterDev\FileWrapper\Exception;

class CannotLoadAsTypeException extends \Exception {
    public function __construct(string $path, string $type) {
        parent::__construct("The file $path could not be parsed as $type.");
    }
}