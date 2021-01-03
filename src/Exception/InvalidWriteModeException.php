<?php

namespace MeesterDev\FileWrapper\Exception;

class InvalidWriteModeException extends \Exception {
    public function __construct(string $mode) {
        parent::__construct("Mode $mode is not a valid write mode.");
    }
}