<?php

namespace MeesterDev\FileWrapper;

use MeesterDev\FileWrapper\Exception\CannotChangePermissionException;
use MeesterDev\FileWrapper\Exception\CannotLoadAsTypeException;
use MeesterDev\FileWrapper\Exception\FileNotFoundException;
use MeesterDev\FileWrapper\Exception\FileOperationException;
use MeesterDev\FileWrapper\Exception\InvalidFileFormatException;
use MeesterDev\FileWrapper\Exception\InvalidOperationTargetException;
use MeesterDev\FileWrapper\Exception\InvalidWriteModeException;
use MeesterDev\FileWrapper\Exception\NotReadableException;
use MeesterDev\FileWrapper\Exception\NotWritableException;
use MeesterDev\FileWrapper\PathResolver\AbstractPathResolver;
use MeesterDev\FileWrapper\PathResolver\PathResolverFactory;
use SplFileInfo;

class File extends SplFileInfo {
    public const  MODE_READ                    = 'r';
    public const  MODE_READ_WRITE              = 'r+';
    public const  MODE_WRITE                   = 'w';
    public const  MODE_WRITE_READ              = 'w+';
    public const  MODE_WRITE_APPEND            = 'a';
    public const  MODE_WRITE_READ_APPEND       = 'a+';
    public const  MODE_WRITE_NO_OVERWRITE      = 'x';
    public const  MODE_WRITE_READ_NO_OVERWRITE = 'x+';
    public const  MODE_WRITE_NO_TRUNCATE       = 'c';
    public const  MODE_WRITE_READ_NO_TRUNCATE  = 'c+';
    public const  MODE_CLOSE_ON_EXEC           = 'e';

    private const WRITE_MODES = [
        self::MODE_READ_WRITE,
        self::MODE_WRITE,
        self::MODE_WRITE_READ,
        self::MODE_WRITE_APPEND,
        self::MODE_WRITE_READ_APPEND,
        self::MODE_WRITE_NO_OVERWRITE,
        self::MODE_WRITE_READ_NO_OVERWRITE,
        self::MODE_WRITE_NO_TRUNCATE,
        self::MODE_WRITE_READ_NO_TRUNCATE,
    ];

    public string                $path = '';
    private AbstractPathResolver $pathResolver;

    public function __construct(?string $path = null) {
        $this->pathResolver = PathResolverFactory::getPathResolver();

        if ($path === null) {
            $this->path = \getcwd();
        }
        else if ($this->pathResolver->isAbsolutePath($path)) {
            $this->path = $path;
        }
        else {
            $this->path = $this->pathResolver->resolve(\getcwd(), $path);
        }

        parent::__construct($this->path);
    }

    // region factory methods

    /**
     * @param string|null $basePath
     * @param string      $prefix
     *
     * @return static
     * @throws FileOperationException
     */
    public static function createTemporary(?string $basePath = null, string $prefix = ''): self {
        if ($basePath === null) {
            $basePath = \sys_get_temp_dir();
        }

        $path = \tempnam($basePath, $prefix);

        if ($path === false) {
            throw new FileOperationException($basePath, FileOperationException::OPERATION_CREATE_TEMPORARY_FILE);
        }

        return new static($path);
    }

    // endregion
    // region scanning

    /**
     * @param bool $recursive
     *
     * @return array
     * @throws InvalidOperationTargetException
     */
    public function directoryContents(bool $recursive = false): array {
        $this->checkIsDir();

        return static::stringsToFiles($this->getDirectoryContents($this->path, $recursive));
    }

    private function getDirectoryContents(string $path, bool $recursive): array {
        $files = [];

        foreach (\scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $this->pathResolver::resolve($path, $item);
            $files[]  = $fullPath;

            if ($recursive && \is_dir($fullPath)) {
                $files = array_merge($files, $this->getDirectoryContents($fullPath, true));
            }
        }

        return $files;
    }

    /**
     * @param string $search
     * @param int    $flags
     *
     * @return static[]
     * @throws InvalidOperationTargetException
     */
    public function glob(string $search, int $flags = 0): array {
        $this->checkIsDir();

        $files = \glob($this->resolvePath($search), $flags);

        return static::stringsToFiles($files);
    }

    // endregion scanning
    // region reading

    /**
     * @param int $flags
     * @param     $context
     *
     * @return array
     * @throws NotReadableException
     */
    public function readLines(int $flags = 0, $context = null): array {
        $this->checkIsReadable();

        return \file($this->path, $flags, $context);
    }

    /**
     * @param $context
     *
     * @return static
     * @throws NotReadableException
     * @throws FileOperationException
     */
    public function readToOutput($context = null): self {
        $this->checkIsReadable();

        if (\readfile($this->path, false, $context) === false) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_READ);
        }

        return $this;
    }

    /**
     * @param          $context
     * @param int      $offset
     * @param int|null $maxLength
     *
     * @return string
     * @throws NotReadableException
     */
    public function contents($context = null, int $offset = 0, int $maxLength = null): string {
        $this->checkIsReadable();

        if ($maxLength === null) {
            return \file_get_contents($this->path, false, $context, $offset);
        }

        return \file_get_contents($this->path, false, $context, $offset, $maxLength);
    }

    public function getMimeType(): ?string {
        if (!$this->isReadable()) {
            return null;
        }

        return \mime_content_type($this->path) ? : null;
    }

    /**
     * @param bool $associative
     * @param int  $depth
     * @param int  $flags
     *
     * @return mixed
     * @throws NotReadableException
     * @throws \JsonException
     */
    public function readJson(bool $associative = false, int $depth = 512, int $flags = 0) {
        return \json_decode($this->contents(), $associative, $depth, $flags | \JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $separator
     * @param string $enclosure
     * @param string $escape
     *
     * @return array
     * @throws NotReadableException
     */
    public function readCsv(string $separator = ',', string $enclosure = '"', string $escape = '\\'): array {
        $this->checkIsReadable();
        $handle = $this->openFile();
        $data   = [];
        do {
            $row = $handle->fgetcsv($separator, $enclosure, $escape);
            if ($row) {
                $data[] = $row;
            }
        } while (!$handle->eof());

        return $data;
    }

    /**
     * @param bool $processSections
     * @param int  $mode
     *
     * @return array
     * @throws InvalidFileFormatException
     * @throws NotReadableException
     */
    public function readIni(bool $processSections = false, int $mode = \INI_SCANNER_NORMAL): array {
        $this->checkIsReadable();

        $parsedFile = \parse_ini_file($this->path, $processSections, $mode);

        if ($parsedFile === false) {
            throw new InvalidFileFormatException($this->path, 'an INI file');
        }

        return $parsedFile;
    }

    /**
     * @param int    $options
     * @param bool   $dataIsUrl
     * @param string $nameSpace
     * @param bool   $isPrefix
     *
     * @return \SimpleXMLElement
     * @throws NotReadableException
     * @throws FileNotFoundException
     */
    public function readXml(int $options = 0, bool $dataIsUrl = false, string $nameSpace = '', bool $isPrefix = false): \SimpleXMLElement {
        $this->checkIsFile();
        $this->checkIsReadable();

        return new \SimpleXMLElement($this->contents(), $options, $dataIsUrl, $nameSpace, $isPrefix);
    }

    /**
     * @param int $xmlFlags
     * @param     $context
     *
     * @return \DOMDocument
     * @throws FileNotFoundException
     * @throws NotReadableException
     * @throws CannotLoadAsTypeException
     */
    public function readDomXml(int $xmlFlags = 0, $context = null): \DOMDocument {
        $this->checkIsFile();
        $this->checkIsReadable();

        $document = new \DOMDocument();

        $result = ($context === null) ? $document->load($this->path, $xmlFlags) : $document->loadXML($this->contents($context), $xmlFlags);
        if ($result === false) {
            throw new CannotLoadAsTypeException($this->path, 'XML');
        }

        return $document;
    }

    /**
     * @param int $xmlFlags
     * @param     $context
     *
     * @return \DOMDocument
     * @throws FileNotFoundException
     * @throws NotReadableException
     * @throws CannotLoadAsTypeException
     */
    public function readDomHtml(int $xmlFlags = 0, $context = null): \DOMDocument {
        $this->checkIsFile();
        $this->checkIsReadable();

        $document = new \DOMDocument();

        $result = ($context === null) ? $document->loadHTMLFile($this->path, $xmlFlags) : $document->loadHTML($this->contents($context), $xmlFlags);
        if ($result === false) {
            throw new CannotLoadAsTypeException($this->path, 'HTML');
        }

        return $document;
    }

    // endregion
    // region writing

    /**
     * @param array  $lines
     * @param string $newLine
     *
     * @return static
     * @throws FileOperationException
     * @throws NotWritableException
     */
    public function writeLines(array $lines, string $newLine = \PHP_EOL): self {
        return $this->put(implode($newLine, $lines));
    }

    /**
     * @param string $content
     * @param int    $flags
     * @param        $context
     *
     * @return static
     * @throws FileOperationException
     * @throws NotWritableException
     */
    public function put(string $content, int $flags = 0, $context = null): self {
        $this->checkIsWritable();

        // we do not want to use the include path, as $this->path will be absolute
        if ($flags & \FILE_USE_INCLUDE_PATH) {
            $flags = $flags & ~\FILE_USE_INCLUDE_PATH;
        }

        if (\file_put_contents($this->path, $content, $flags, $context) === false) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_WRITE);
        }

        return new static($this->path);
    }

    /**
     * @param     $contents
     * @param int $depth
     * @param int $flags
     *
     * @return static
     * @throws FileOperationException
     * @throws NotWritableException
     * @throws \JsonException
     */
    public function writeJson($contents, int $depth = 512, int $flags = 0): self {
        return $this->put(\json_encode($contents, $flags | \JSON_THROW_ON_ERROR, $depth));
    }

    /**
     * @param array  $rows
     * @param string $mode
     * @param string $separator
     * @param string $enclosure
     * @param string $escape
     *
     * @return static
     * @throws InvalidWriteModeException
     * @throws NotWritableException
     */
    public function writeCsv(array $rows, string $mode = self::MODE_WRITE, string $separator = ',', string $enclosure = '"',
        string $escape = '\\'): self {
        $this->checkIsWritable();

        if (!in_array($mode, static::WRITE_MODES)) {
            throw new InvalidWriteModeException($mode);
        }

        $handle = $this->openFile($mode);
        foreach ($rows as $row) {
            $handle->fputcsv($row, $separator, $enclosure, $escape);
        }

        return new static($this->path);
    }

    /**
     * @param \SimpleXMLElement $xml
     *
     * @return static
     * @throws FileOperationException
     * @throws NotWritableException
     */
    public function writeXml(\SimpleXMLElement $xml): self {
        $this->checkIsWritable();

        $result = $xml->asXML($this->path);

        if ($result === false) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_WRITE);
        }

        return new static($this->path);
    }

    /**
     * @param \DOMDocument  $xml
     * @param \DOMNode|null $nodeToUseAsRoot
     * @param int           $xmlFlags
     *
     * @return static
     * @throws FileOperationException
     * @throws NotWritableException
     */
    public function writeDomXml(\DOMDocument $xml, ?\DOMNode $nodeToUseAsRoot = null, int $xmlFlags = 0): self {
        $this->checkIsWritable();

        $result = $this->put($xml->saveXML($nodeToUseAsRoot, $xmlFlags));

        if ($result === false) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_WRITE);
        }

        return new static($this->path);
    }

    /**
     * @param \DOMDocument  $document
     * @param \DOMNode|null $root
     *
     * @return File
     * @throws FileOperationException
     * @throws NotWritableException
     */
    public function writeDomHtml(\DOMDocument $document, ?\DOMNode $root = null): self {
        $this->checkIsWritable();

        if ($root === null) {
            $result = $document->saveHtmlFile($this->path);
        }
        else {
            $result = $document->saveHTML($root);
            if ($result !== false) {
                try {
                    $this->put($result);
                }
                catch (NotWritableException $e) {
                    $result = false;
                }
            }
        }
        if ($result === false) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_WRITE);
        }

        return new static($this->path);
    }

    // endregion
    // region file operations

    /**
     * @param string|int $group
     *
     * @return static
     * @throws CannotChangePermissionException
     * @throws FileNotFoundException
     */
    public function chgrp($group): self {
        $this->checkExists();

        if ($this->isLink()) {
            if (\lchgrp($this->path, $group) === false) {
                throw new CannotChangePermissionException($this->path);
            }
        }
        else {
            if (\chgrp($this->path, $group) === false) {
                throw new CannotChangePermissionException($this->path);
            }
        }

        return new static($this->path);
    }

    /**
     * @param int $permissions
     *
     * @return static
     * @throws CannotChangePermissionException
     * @throws FileNotFoundException
     */
    public function chmod(int $permissions): self {
        $this->checkExists();

        if (\chmod($this->path, $permissions) === false) {
            throw new CannotChangePermissionException($this->path);
        }

        return new static($this->path);
    }

    /**
     * @param string|int $user
     * @param null       $group
     *
     * @return static
     * @throws CannotChangePermissionException
     * @throws FileNotFoundException
     */
    public function chown($user, $group = null): self {
        $this->checkExists();

        if ($this->isLink()) {
            if (\lchown($this->path, $user) === false) {
                throw new CannotChangePermissionException($this->path);
            }
        }
        else {
            if (\chown($this->path, $user) === false) {
                throw new CannotChangePermissionException($this->path);
            }
        }

        return $group === null ? new static($this->path) : $this->chgrp($group);
    }

    /**
     * @param string $destination
     * @param bool   $relativeFromCurrentFile
     * @param        $context
     *
     * @return static
     * @throws FileNotFoundException
     * @throws FileOperationException
     */
    public function copy(string $destination, bool $relativeFromCurrentFile = true, $context = null): self {
        $this->checkExists();

        if ($relativeFromCurrentFile) {
            $destination = $this->resolvePath($destination);
        }

        if (\copy($this->path, $destination, $context) === false) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_COPY);
        }

        return new static($destination);
    }

    /**
     * @return float|null
     * @throws FileNotFoundException
     */
    public function diskFreeSpace(): ?float {
        $this->checkExists();

        $space = \disk_free_space($this->isFile() ? $this->getPath() : $this->path);

        return $space === false ? null : $space;
    }

    /**
     * @return float|null
     * @throws FileNotFoundException
     */
    public function diskTotalSpace(): ?float {
        $this->checkExists();

        $space = \disk_total_space($this->isFile() ? $this->getPath() : $this->path);

        return $space === false ? null : $space;
    }

    /**
     * @param string $pattern
     * @param int    $flags
     *
     * @return bool
     */
    public function fileNameMatches(string $pattern, int $flags = 0): bool {
        return \fnmatch($pattern, $this->getFilename(), $flags);
    }

    /**
     * @param string $linkName
     * @param bool   $relativeFromCurrentFile
     *
     * @return static
     * @throws FileNotFoundException
     * @throws FileOperationException
     */
    public function link(string $linkName, bool $relativeFromCurrentFile = true): self {
        $this->checkExists();
        $this->checkIsHardLinkable();

        if ($relativeFromCurrentFile) {
            $linkName = $this->resolvePath($linkName);
        }

        if (\link($this->path, $linkName) === false) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_LINK_TO);
        }

        return new static($linkName);
    }

    /**
     * @param string $linkName
     * @param bool   $relativeFromCurrentFile
     *
     * @return static
     * @throws FileNotFoundException
     * @throws FileOperationException
     */
    public function symlink(string $linkName, bool $relativeFromCurrentFile = true): self {
        $this->checkExists();
        $this->checkIsSoftLinkable();

        if ($relativeFromCurrentFile) {
            $linkName = $this->resolvePath($linkName);
        }

        if (\symlink($this->path, $linkName) === false) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_LINK_TO);
        }

        return new static($linkName);
    }

    /**
     * @return int|null
     * @throws InvalidOperationTargetException
     */
    public function linkInfo(): ?int {
        $this->checkIsLink();

        return \linkinfo($this->path) ? : null;
    }

    /**
     * @return static
     * @throws FileOperationException
     * @throws InvalidOperationTargetException
     */
    public function linkTarget(): self {
        $this->checkIsLink();

        $target = \readlink($this->path);

        if ($target === false) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_FIND_LINK_TARGET);
        }

        return new static($target);
    }

    /**
     * @return ?array
     * @throws FileNotFoundException
     */
    public function lstat(): ?array {
        $this->checkExists();

        return \lstat($this->path) ? : null;
    }

    /**
     * @param string $name
     * @param int    $mode
     * @param bool   $recursive
     * @param        $context
     *
     * @return static
     * @throws FileOperationException
     * @throws InvalidOperationTargetException
     */
    public function mkdir(string $name, int $mode = 0777, bool $recursive = false, $context = null): self {
        $this->checkIsDir();

        if (\mkdir($this->resolvePath($name), $mode, $recursive, $context) === false) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_CREATE_SUBDIRECTORY);
        }

        return $this->cd($name);
    }

    /**
     * @param string $to
     * @param bool   $relativeFromCurrentFile
     *
     * @return static
     * @throws FileNotFoundException
     * @throws FileOperationException
     */
    public function moveUploadedFile(string $to, bool $relativeFromCurrentFile = true): self {
        $this->checkIsFile();

        if (!$this->isUploadedFile()) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_MOVE_UPLOADED_FILE);
        }

        if ($relativeFromCurrentFile) {
            $to = $this->resolvePath($to);
        }

        \move_uploaded_file($this->path, $to);

        return new static($to);
    }

    /**
     * @param string $to
     * @param bool   $relativeFromCurrentFile
     * @param        $context
     *
     * @return static
     * @throws FileNotFoundException
     * @throws FileOperationException
     */
    public function rename(string $to, bool $relativeFromCurrentFile = true, $context = null): self {
        $this->checkExists();

        if ($relativeFromCurrentFile) {
            $to = $this->resolvePath($to);
        }

        $result = \rename($this->path, $to, $context);

        if ($result === false) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_RENAME);
        }

        return new static($to);
    }

    /**
     * @param bool $recursive
     * @param      $context
     *
     * @return static
     * @throws FileNotFoundException
     * @throws FileOperationException
     * @throws NotWritableException
     */
    public function delete(bool $recursive = false, $context = null): self {
        $this->checkExists();
        $this->checkIsWritable();

        if ($this->isLink() || $this->isFile() || ($this->isDir() && $recursive === false)) {
            $result = \unlink($this->path, $context);
        }
        elseif ($this->isDir()) {
            $result = $this->recursiveDelete($this->path, $context);
        }
        else {
            $result = false;
        }

        if ($result === false) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_DELETE);
        }

        return new static($this->path);
    }

    /**
     * @param string $path
     * @param        $context
     *
     * @return bool
     * @throws FileOperationException
     */
    private function recursiveDelete(string $path, $context = null): bool {
        $contents = \scandir($path);

        if ($contents === false) {
            return false;
        }

        $allOk = true;

        foreach ($contents as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $this->pathResolver::resolve($path, $item);

            if (\is_dir($fullPath)) {
                $allOk = $this->recursiveDelete($fullPath);
            }
            else {
                $allOk = $allOk && \unlink($fullPath, $context);
            }
        }

        return $allOk && \rmdir($path);
    }

    /**
     * @return array|null
     * @throws FileNotFoundException
     */
    public function stat(): ?array {
        $this->checkExists();

        return \stat($this->path) ? : null;
    }

    /**
     * @param int|null $time
     * @param int|null $atime
     *
     * @return static
     * @throws FileOperationException
     */
    public function touch(?int $time = null, ?int $atime = null): self {
        try {
            $this->checkParentExists();
        }
        catch (FileNotFoundException $e) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_TOUCH);
        }

        if ($time === null) {
            $time = time();
        }

        if ($atime === null) {
            $atime = $time;
        }

        $result = \touch($this->path, $time, $atime);

        if ($result === false) {
            throw new FileOperationException($this->path, FileOperationException::OPERATION_TOUCH);
        }

        return new static($this->path);
    }

    // endregion
    // region pathing

    public function select(string $path): self {
        return $this->go($path);
    }

    public function cd(string $path): self {
        return $this->go($path);
    }

    public function go(string $path): self {
        return new static($this->resolvePath($path));
    }

    protected function resolvePath(string $path): string {
        if (\file_exists($this->path) && !$this->isDir()) {
            $basePath = $this->getPath();
        }
        else {
            $basePath = $this->path;
        }

        return $this->pathResolver->resolve($basePath, $path);
    }

    public function relativePath(bool $force = false): string {
        return $this->pathResolver->relativePath(\getcwd(), $this->path, $force);
    }

    // endregion
    // region checks

    protected function isUploadedFile(): bool {
        return \is_uploaded_file($this->path);
    }

    /**
     * @throws FileNotFoundException
     */
    protected function checkIsFile(): void {
        if (!$this->isFile()) {
            throw new FileNotFoundException($this->path);
        }
    }

    /**
     * @throws NotReadableException
     */
    protected function checkIsReadable(): void {
        if (!$this->isReadable()) {
            throw new NotReadableException($this->path);
        }
    }

    /**
     * @throws NotWritableException
     */
    protected function checkIsWritable(): void {
        $exists = \file_exists($this->path);

        if ($exists) {
            // the file itself must be writable
            if (!$this->isWritable()) {
                throw new NotWritableException($this->path);
            }
        }
        else {
            // the parent must exist and be writable
            $parent = $this->go('..');
            try {
                $parent->checkExists();
            }
            catch (FileNotFoundException $e) {
                throw new NotWritableException($this->path);
            }
            $parent->checkIsWritable();
        }
    }

    /**
     * @throws InvalidOperationTargetException
     */
    protected function checkIsDir(): void {
        if (!$this->isDir()) {
            throw new InvalidOperationTargetException($this->path, 'directories');
        }
    }

    /**
     * @throws InvalidOperationTargetException
     */
    protected function checkIsLink(): void {
        if (!$this->isLink()) {
            throw new InvalidOperationTargetException($this->path, 'links');
        }
    }

    /**
     * @throws FileNotFoundException
     */
    protected function checkExists(): void {
        if (!\file_exists($this->path)) {
            throw new FileNotFoundException($this->path);
        }
    }

    /**
     * @throws FileNotFoundException
     */
    protected function checkParentExists(): void {
        if (!\file_exists($this->getPath())) {
            throw new FileNotFoundException($this->getPath());
        }
    }

    /**
     * @throws FileNotFoundException
     */
    protected function checkIsHardLinkable(): void {
        $this->checkIsFile();
    }

    /**
     * @throws FileNotFoundException
     */
    protected function checkIsSoftLinkable(): void {
        $this->checkExists();
    }

    // endregion
    // region helpers

    protected static function stringsToFiles(array $paths): array {
        return \array_map(
            function (string $file): self {
                return new static($file);
            },
            $paths
        );
    }

    public function __toString(): string {
        return $this->path;
    }

    // endregion
}