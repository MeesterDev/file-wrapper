<?php

namespace MeesterDev\FileWrapper\Exception;

class CannotChangePermissionException extends \Exception {
    public function __construct(string $path) {
        parent::__construct("Cannot change permissions of $path.");
    }
}