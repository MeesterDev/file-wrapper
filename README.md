# File wrapper
An object-oriented way of dealing with files and performing actions on them.

## Usage
### Example

```php
<?php

use MeesterDev\FileWrapper\File;

$file = new File();
$file->cd('..');
$file->select('data.json');
$json = $file->readJson();
```

### All available public methods/properties
All methods from [SplFileInfo](https://www.php.net/manual/en/class.splfileinfo.php) are available. Further methods are described below.

#### A note about what a file is
An instance of `File` can point to anything: directories, files, links, and even things that do not exist.

There is no distinction made between files and directories, until actions are performed that require either type.

#### Properties
##### string $path
The current path the file is pointing to.

#### Constructors
##### new File([string $file]);
The default constructor. You can use both a relative and absolute path. Note that a relative path is applied from the current working directory (`getcwd()`).

##### File::createTemporary(string $directory = sys_get_temp_dir(), string $prefix = '')
Create a temporary file in the given directory. The name will be prefixed with `$prefix`. This relies on the PHP functionality [`tmpnam()`](https://www.php.net/manual/en/function.tempnam.php), which may or may not do exactly follow the given arguments. Regardless, the returned `File`'s `$path` property will be set to the created file.

#### Scanning
##### $file->directoryContents(bool $recursive = false): File[]
Scans and returns all directories and files contained in the file. This will only work if the file itself is a directory.

##### $file->glob(string $search, int $flags = 0): File[]
Scans and returns all directories and files contained in the file that match the `$search` string. This will only work if the file itself is a directory. `$flags` will be passed directly to the native [`glob()`](https://www.php.net/manual/en/function.glob.php) function.

#### Reading
##### $file->readLines(int $flags, $context = null): File[]
Returns an array of the lines contained in the file. This only works on files. `$flags` and `$context` are passed directly the native [`file()`](https://www.php.net/manual/en/function.file) function.

##### $file->readToOutput(int $flags, $context = null): File
Prints the file to STDOUT. The `$context` is passed directly to the native [`readfile()`](https://www.php.net/manual/en/function.readfile.php) function.

##### $file->contents($context = null, int $offset, int $maxLength = null): string
Returns the contents of the file. All arguments are passed directly to [`file_get_contents()`](https://www.php.net/manual/en/function.file-get-contents).

##### $file->getMimeType(): string
Returns the mimetype of the file.

##### $file->readJson(bool $associative = false, int $depth = 512, int $flags = 0)
Parses the file as JSON and returns the parsed content. All arguments are passed to the native [`json_decode()`](https://www.php.net/manual/en/function.json-encode.php) function. Note that the [`JSON_THROW_ON_ERROR`](https://www.php.net/manual/en/json.constants.php#constant.json-throw-on-error) flag is always set.

##### $file->readCsv(string $separator = ',', string $enclosure = '"', string $escape = '\\'): mixed[][]
Returns an array of rows from the CSV file. The file is loaded in its entirety. All arguments are passed to the native [`fgetcsv()`](https://www.php.net/manual/en/splfileobject.fgetcsv) method.

##### $file->readIni(bool $processSections, int $mode = INI_SCANNER_NORMAL): array
Parses the file as an INI file and returns the result. The arguments are passed directly to the native [`parse_ini_file()`](https://www.php.net/manual/en/function.parse-ini-file) function.

##### $file->readXml(int $options = 0, bool $dataIsUrl = false, string $namespace = '', bool $isPrefix = false): SimpleXMLElement
Returns a SimpleXMLElement constructed from the file's content. All arguments are directly passed to the [constructor](https://www.php.net/manual/en/simplexmlelement.construct).

##### $file->readDomXml(int $xmlFlags = 0, $context = null): DOMDocument
Returns a DOMDocument constructed from the file's content. `$xmlFlags` is passed to the `DOMDocument`'s [`loadXML()`](https://www.php.net/manual/en/domdocument.loadxml.php) method. `$context` is passed to the `$file`'s `contents` method.

##### $file->readDomHtml(int $xmlFlags = 0, $context = null): DOMDocument
Same as above, but uses the HTML loading methods of DOMDocument.

#### Writing
##### $file->writeLines(string[] $lines, string $newLine = PHP_EOL): File
Writes the lines to the file.

##### $file->put(string $content, int $flags, $contest): File
Writes the content to the file. The arguments are passed directly to [`file_put_contents()`](https://www.php.net/manual/en/function.file-put-contents).

##### $file->writeJson($contents, int $depth = 512, int $flags = 0): File
Encodes $contents as JSON and writes it to the file. The `$depth` and `$flags` are passed directly the [`json_encode()`](https://www.php.net/manual/en/function.json-encode). Note that the [`JSON_THROW_ON_ERROR`](https://www.php.net/manual/en/json.constants.php#constant.json-throw-on-error) flag is always set.

##### $file->writeCsv(mixed[][] $rows, string $mode = File::MODE_WRITE, string $separator = ',', string $enclosure = '"', string $escape = '\\'): File
Writes the rows to the file as CSV. `$mode` is the file mode used to open the file with for writing. `$separator`, `$enclosure`, and `$escape` are passed to the [`fputcsv()`](https://www.php.net/manual/en/splfileobject.fputcsv) method.

##### $file->writeXml(SimpleXMLElement $xml): File
Writes the SimpleXMLElement to the file (as XML).

##### $file->writeDomXml(DOMDocument $document, ?\DOMNode $rootNode = null, int $flags = 0): File
Writes the DOMDocument to the file (as XML). The `$rootNode` and `$flags` arguments are passed to the [`saveXML()`](https://www.php.net/manual/en/domdocument.savexml) method.

##### $file->writeDomHtml(DOMDocument $document, ?\DOMNode $rootNode = null): File
Same as above, but uses the HTML saving methods of DOMDocument.

#### File operations
##### $file->chgrp(string|int $group): File
Changes the group of the file. Usually only possible as root.

##### $file->chmod(int $permissions): File
Changes the permissions of the file. Usually only possible as the owner or as root.

##### $file->chown(string|int $user, string|int|null $group = null): File
Changes the owner and group (if supplied) of the file. Usually only possible as root.

##### $file->copy(string $destination, bool $relativeFromCurrentFile = true, $context = null): File
Copies the file and returns a new instance pointing to the new file. You can set `$relativeFromCurrentFile` to false to tell the class to not do any modifications with `$destination`, in which case the argument is passed directly to the native [`copy()`](https://www.php.net/manual/en/function.copy.php) function. The `$context` is passed regardless.

##### $file->link(string $name, bool $relativeFromCurrentFile = true): File
Creates a hard link. This only works on files. You can set `$relativeFromCurrentFile` to false to tell the class to not do any modifications with `$name`, in which case the argument is passed directly to the native [`link()`](https://www.php.net/manual/en/function.link) function.

##### $file->symlink(string $name, bool $relativeFromCurrentFile = true): File
Same as above, but creates a symbolic link, thus making it work on other things too (e.g. directories).

##### $file->linkInfo(): ?int
Returns the link info of the file. See [`linkinfo()`](https://www.php.net/manual/en/function.linkinfo).

##### $file->linkTarget(): File
Returns a new File pointing to the target of a (symbolic) link.

##### $file->lstat(): ?array
Returns the result of lstat of the file. See [`lstat()`](https://www.php.net/manual/en/function.lstat).

##### $file->mkdir(): File
Creates a subdirectory of the file. The current file must be a directory. All arguments are passed to [`mkdir()`](https://www.php.net/manual/en/function.mkdir.php).

##### $file->moveUploadedFile(string $to, bool $relativeFromCurrentFile = true): File
Moves the file to `$to`, if it is an uploaded file. You can set `$relativeFromCurrentFile` to false to tell the class to not do any modifications with `$destination`, in which case the argument is passed directly to the native [`move_uploaded_file()`](https://www.php.net/manual/en/function.move-uploaded-file) function. Returns the new file.

##### $file->rename(string $to, bool $relativeFromCurrentFile = true, $context = null): File
Renames the file to `$to`. You can set `$relativeFromCurrentFile` to false to tell the class to not do any modifications with `$destination`, in which case the argument is passed directly to the native [`rename()`](https://www.php.net/manual/en/function.rename.php) function. `$context` is always passed. Returns the new file.

##### $file->delete(bool $recursive, $context = null): File
Deletes the file if it is a file, or the directory if it is a directory and `$recursive` is `true`. The `$context` is passed to all calls made to [`unlink()`](https://www.php.net/manual/en/function.unlink.php). If deletion of any file fails, this is silently ignored until all deletions have been attempted, at which point an exception is thrown. If scanning of a directory fails, the same logic applies.

##### $file->stat(): ?array
Returns the result of lstat of the file. See [`stat()`](https://www.php.net/manual/en/function.stat.php).

##### $file->touch(int $time = time(), int $atime = $time): File
Touches a file, creating it. The arguments are passed to [`touch()`](https://www.php.net/manual/en/function.touch.php).

#### Pathing
##### $file->select($path): File, $file->go($path): File, $file->cd($path): File
Returns a new File pointing to the new path. The new path is built from the current path and the given path.

##### $file->relativePath(bool $force = false): string
Returns a printable relative path for the file. This is always relative to the current working directory. If `$force` is set to true, the path will be relative, even if the first folder is different.

On Windows, if the file and working directory are on different drives, an absolute path is returned, even if `$force` is set to true.

#### Other

##### $file->fileNameMatches(string $pattern, int $flags = 0): bool
Returns true if the file name of the File matches the given pattern. The arguments are passed directly to [`fnmatch()`](https://www.php.net/manual/en/function.fnmatch).

##### $file->diskFreeSpace(): ?float
Returns the available disk space, if PHP can determine it.

##### $file->diskTotalSpace(): ?float
Returns the total disk space, if PHP can determine it.

##### $file->__toString(): string
Returns `$file->path`.
