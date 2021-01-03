<?php

namespace MeesterDev\FileWrapper\Exception;

class InvalidOperationTargetException extends \Exception {
    public function __construct(string $path, string $type) {
        parent::__construct("Operation attempted on $path is not possible. It can only be performed on $type.");
    }
}