<?php

namespace MeesterDev\FileWrapper\Exception;

class InvalidFileFormatException extends \Exception {
    public function __construct(string $path, string $type) {
        parent::__construct("The file $path is not $type.");
    }
}