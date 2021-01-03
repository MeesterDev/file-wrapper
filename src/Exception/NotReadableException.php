<?php

namespace MeesterDev\FileWrapper\Exception;

class NotReadableException extends \Exception {
    public function __construct(string $path) {
        parent::__construct("The file $path is not readable.");
    }
}