<?php

namespace MeesterDev\FileWrapper\Exception;

class FileNotFoundException extends \Exception {
    public function __construct(string $path) {
        parent::__construct("File/directory $path does not exist.");
    }
}