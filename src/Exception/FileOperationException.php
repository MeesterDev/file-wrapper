<?php

namespace MeesterDev\FileWrapper\Exception;

class FileOperationException extends \Exception {
    public const OPERATION_WRITE                 = 'write to';
    public const OPERATION_COPY                  = 'copy';
    public const OPERATION_RENAME                = 'rename';
    public const OPERATION_TOUCH                 = 'touch';
    public const OPERATION_DELETE                = 'delete';
    public const OPERATION_CREATE_TEMPORARY_FILE = 'create temporary file in';
    public const OPERATION_LINK_TO               = 'link to';
    public const OPERATION_FIND_LINK_TARGET      = 'find link target of';
    public const OPERATION_CREATE_SUBDIRECTORY   = 'create subdirectory of';
    public const OPERATION_MOVE_UPLOADED_FILE    = 'move uploaded file';
    public const OPERATION_READ                  = 'read';

    public string $operation;

    public function __construct(string $path, string $operation) {
        $this->operation = $operation;

        parent::__construct("Cannot $operation $path.");
    }
}