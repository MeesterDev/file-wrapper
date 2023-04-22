<?php

use MeesterDev\FileWrapper\Exception\CannotChangePermissionException;
use MeesterDev\FileWrapper\Exception\CannotLoadAsTypeException;
use MeesterDev\FileWrapper\Exception\FileNotFoundException;
use MeesterDev\FileWrapper\Exception\FileOperationException;
use MeesterDev\FileWrapper\Exception\InvalidFileFormatException;
use MeesterDev\FileWrapper\Exception\InvalidOperationTargetException;
use MeesterDev\FileWrapper\Exception\InvalidWriteModeException;
use MeesterDev\FileWrapper\Exception\NotReadableException;
use MeesterDev\FileWrapper\Exception\NotWritableException;
use MeesterDev\FileWrapper\File;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase {
    private const TEST_DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR . 'test_folder';

    /**
     * @throws FileNotFoundException
     * @throws FileOperationException
     * @throws NotWritableException
     */
    public function testTemporaryFileConstructor() {
        $aDirectory = static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'a';

        $file = File::createTemporary($aDirectory);
        $this->assertTrue(file_exists($file->path));
        $this->assertHasMTime($file);
        $file = $file->delete();
        $this->assertFalse(file_exists($file->path));
        $this->assertDoesntHaveMTime($file);

        $file = File::createTemporary(null, 'prefix');
        $this->assertTrue(file_exists($file->path));
        $this->assertHasMTime($file);
        $file = $file->delete();
        $this->assertFalse(file_exists($file->path));
        $this->assertDoesntHaveMTime($file);

        $file = File::createTemporary($aDirectory, 'p');
        $this->assertTrue(file_exists($file->path));
        $this->assertStringStartsWith($aDirectory . DIRECTORY_SEPARATOR . 'p', $file->path);
        $this->assertHasMTime($file);
        $file = $file->delete();
        $this->assertFalse(file_exists($file->path));
        $this->assertDoesntHaveMTime($file);
    }

    public function testNormalConstructor() {
        $aDirectory = static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'a';
        $workingDirectory = getcwd();

        chdir(static::TEST_DIRECTORY);
        $file = new File('a');
        $this->assertEquals($aDirectory, $file->path);
        $file = new File($aDirectory);
        $this->assertEquals($aDirectory, $file->path);
        chdir($workingDirectory);
    }

    /**
     * @throws InvalidOperationTargetException
     */
    public function testContentScanning(): void {
        $aDirectory = static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'a';

        $file     = new File($aDirectory);
        $contents = $file->directoryContents(true);
        $this->assertTrue(
            $this->arraysHaveSameContent(
                [
                    implode(DIRECTORY_SEPARATOR, [$aDirectory, '0', 'a']),
                    implode(DIRECTORY_SEPARATOR, [$aDirectory, '0']),
                    implode(DIRECTORY_SEPARATOR, [$aDirectory, '1']),
                ],
                array_column($contents, 'path')
            )
        );
        $contents = $file->directoryContents(false);
        $this->assertTrue(
            $this->arraysHaveSameContent(
                [
                    implode(DIRECTORY_SEPARATOR, [$aDirectory, '0']),
                    implode(DIRECTORY_SEPARATOR, [$aDirectory, '1']),
                ],
                array_column($contents, 'path')
            )
        );
    }

    /**
     * @throws InvalidOperationTargetException
     */
    public function testGlob(): void {
        $aDirectory = static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'a';

        $file         = new File($aDirectory);
        $globContents = $file->glob('*');
        $this->assertTrue(
            $this->arraysHaveSameContent(
                [
                    implode(DIRECTORY_SEPARATOR, [$aDirectory, '0']),
                    implode(DIRECTORY_SEPARATOR, [$aDirectory, '1']),
                ],
                array_column($globContents, 'path')
            )
        );
        $globContents = $file->glob('*' . DIRECTORY_SEPARATOR . 'a');
        $this->assertTrue(
            $this->arraysHaveSameContent(
                [
                    implode(DIRECTORY_SEPARATOR, [$aDirectory, '0', 'a']),
                ],
                array_column($globContents, 'path')
            )
        );
    }

    /**
     * @throws JsonException
     * @throws NotReadableException
     */
    public function testReadJson(): void {
        $jsonPath = static::TEST_DIRECTORY . '/a/0/test-read.json';
        file_put_contents($jsonPath, json_encode(['test' => 3.141]));

        $file = new File($jsonPath);
        $data = $file->readJson();
        $this->assertIsObject($data);
        $this->assertTrue(property_exists($data, 'test'));
        $this->assertEquals(3.141, $data->test);
        $data = $file->readJson(true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('test', $data);
        $this->assertEquals(3.141, $data['test']);

        unlink($jsonPath);
    }

    /**
     * @throws NotReadableException
     */
    public function testReadCsv(): void {
        $csvPath = implode(DIRECTORY_SEPARATOR, [static::TEST_DIRECTORY, 'a', '0', 'test-read.csv']);
        file_put_contents($csvPath, 'q,b,c,d,e' . PHP_EOL . '11,2,3,4,5');

        $file = new File($csvPath);
        $data = $file->readLines();
        $this->assertTrue(
            $this->arraysHaveSameContent(
                [
                    'q,b,c,d,e',
                    '11,2,3,4,5',
                ],
                array_map('trim', $data)
            )
        );

        $this->assertEquals([['q', 'b', 'c', 'd', 'e'], ['11', '2', '3', '4', '5']], $file->readCsv());

        unlink($csvPath);
    }

    /**
     * @throws NotReadableException
     */
    public function testReadGeneric(): void {
        $readPath = implode(DIRECTORY_SEPARATOR, [static::TEST_DIRECTORY, 'a', '0', 'test-read.gen']);
        $contents = 'a,b,c,d,f' . PHP_EOL . '1,2,3,4,6';
        file_put_contents($readPath, $contents);

        $file = new File($readPath);
        ob_start();
        $file->readToOutput();
        $content = ob_get_clean();
        $this->assertEquals($contents, $content);

        $this->assertEquals($contents, $file->contents());
        $n = 10 + strlen(PHP_EOL);
        $this->assertEquals(substr($contents, 1, $n), $file->contents(null, 1, $n));
        $this->assertStringStartsWith('text/', $file->getMimeType());

        unlink($readPath);
    }

    /**
     * @throws InvalidFileFormatException
     * @throws NotReadableException
     */
    public function testReadIni(): void {
        file_put_contents(
            static::TEST_DIRECTORY . '/a/0/test.ini',
            implode(PHP_EOL, ['; comment', '', '[section]', 'data = 3.141', 'more_data = "pi"'])
        );

        $file = new File(implode(DIRECTORY_SEPARATOR, [static::TEST_DIRECTORY, 'a', '0', 'test.ini']));
        $this->assertEquals(['data' => 3.141, 'more_data' => 'pi'], $file->readIni());
        $this->assertEquals(['section' => ['data' => 3.141, 'more_data' => 'pi']], $file->readIni(true));

        unlink(static::TEST_DIRECTORY . '/a/0/test.ini');
    }

    /**
     * @throws FileNotFoundException
     * @throws NotReadableException
     * @throws CannotLoadAsTypeException
     */
    public function testReadXml(): void {
        $xmlPath = implode(DIRECTORY_SEPARATOR, [static::TEST_DIRECTORY, 'a', '0', 'test-read.xml']);
        file_put_contents(
            $xmlPath,
            '<?xml version="1.0" ?><randomNode><randomData name="pi">3.141</randomData><randomData name="random">43658923</randomData></randomNode>'
        );

        $file       = new File($xmlPath);
        $xmlElement = $file->readXml();
        $this->assertInstanceOf(SimpleXMLElement::class, $xmlElement);
        /** @var SimpleXMLElement[] $children */
        $children = $xmlElement->children();
        $this->assertCount(2, $children);
        $this->assertEquals('pi', $children[0]->attributes()->name);
        $this->assertEquals('random', $children[1]->attributes()->name);
        $this->assertEquals(3.141, (float) $xmlElement->randomData[0]);
        $this->assertEquals(43658923, (float) $xmlElement->randomData[1]);

        $domDocument = $file->readDomXml();
        $this->assertInstanceOf(DOMDocument::class, $domDocument);
        $this->assertEquals(1, $domDocument->childNodes->length);
        $this->assertEquals('randomNode', $domDocument->childNodes[0]->tagName);

        unlink($xmlPath);
    }

    /**
     * @throws CannotLoadAsTypeException
     * @throws FileNotFoundException
     * @throws NotReadableException
     */
    public function testReadHtml(): void {
        $htmlPath = implode(DIRECTORY_SEPARATOR, [static::TEST_DIRECTORY, 'a', '0', 'test-read.html']);
        file_put_contents(
            $htmlPath,
            '<!DOCTYPE html><html lang="en"><head><title>test</title></head><body><div>some text</div></body></html>'
        );

        $file        = new File($htmlPath);
        $domDocument = $file->readDomHtml();
        $this->assertInstanceOf(DOMDocument::class, $domDocument);
        $this->assertEquals(2, $domDocument->childNodes->length);
        $this->assertEquals('html', $domDocument->childNodes[1]->tagName);
        $this->assertEquals(2, $domDocument->childNodes[1]->childNodes->length);
        $this->assertEquals('head', $domDocument->childNodes[1]->childNodes[0]->tagName);

        unlink($htmlPath);
    }

    /**
     * @throws FileOperationException
     * @throws NotWritableException
     */
    public function testWriteLines(): void {
        $path = implode(DIRECTORY_SEPARATOR, [static::TEST_DIRECTORY, 'a', '0', 'test-write']);
        $lines = ['first line', 'second line'];

        $file = new File($path);
        $file->writeLines($lines);
        $this->assertEquals($lines[0] . PHP_EOL . $lines[1], file_get_contents($path));
        $separator = "\r\nDUMMY ";
        $file->writeLines($lines, $separator);
        $this->assertEquals($lines[0] . $separator . $lines[1], file_get_contents($path));

        unlink($path);
    }

    /**
     * @throws InvalidWriteModeException
     * @throws NotWritableException
     */
    public function testWriteCsv(): void {
        $path = implode(DIRECTORY_SEPARATOR, [static::TEST_DIRECTORY, 'a', '0', 'test-write.csv']);

        $file = new File($path);
        $file->writeCsv([['a', 'b', 'c', 'd', 'e'], ['1', '2', '3', '4', '5']]);
        $this->assertEquals("a,b,c,d,e\n1,2,3,4,5\n", file_get_contents($path));
        $file->writeCsv([['a', 'b', 'c', 'd', 'e a.k.a. "fake %"'], ['1', '2', '3', '4', '5']], File::MODE_WRITE, '|', '%', ']');
        $this->assertEquals("a|b|c|d|%e a.k.a. \"fake %%\"%\n1|2|3|4|5\n", file_get_contents($path));

        unlink($path);
    }

    /**
     * @throws FileOperationException
     * @throws NotWritableException
     */
    public function testWriteGeneric(): void {
        $path = implode(DIRECTORY_SEPARATOR, [static::TEST_DIRECTORY, 'a', '0', 'test.txt']);

        $file = new File($path);
        $file->put('this is fake text');
        $this->assertEquals('this is fake text', file_get_contents($path));

        unlink(static::TEST_DIRECTORY . '/a/0/test.txt');
    }

    /**
     * @throws FileOperationException
     * @throws JsonException
     * @throws NotWritableException
     */
    public function testWriteJson(): void {
        $path = implode(DIRECTORY_SEPARATOR, [static::TEST_DIRECTORY, 'a', '0', 'test.json']);
        $file = new File($path);

        $file->writeJson(['hello' => 'goodbye']);
        $this->assertEquals('{"hello":"goodbye"}', file_get_contents($path));
        $file->writeJson(['hello' => 'goodbye'], 512, JSON_PRETTY_PRINT);
        $this->assertEquals("{\n    \"hello\": \"goodbye\"\n}", file_get_contents($path));

        unlink(static::TEST_DIRECTORY . '/a/0/test.json');
    }

    /**
     * @throws FileOperationException
     * @throws NotWritableException
     */
    public function testWriteXml(): void {
        $xml  = '<?xml version="1.0" ?><a><b><c>text</c></b><d></d></a>';
        $path = implode(DIRECTORY_SEPARATOR, [static::TEST_DIRECTORY, 'a', '0', 'test.xml']);

        $file = new File($path);

        $file->writeXml(new SimpleXMLElement($xml));
        $this->assertEquals("<?xml version=\"1.0\"?>\n<a><b><c>text</c></b><d/></a>\n", file_get_contents($path));

        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $file->writeDomXml($dom);
        $this->assertEquals("<?xml version=\"1.0\"?>\n<a><b><c>text</c></b><d/></a>\n", file_get_contents($path));

        unlink(static::TEST_DIRECTORY . '/a/0/test.xml');
    }

    /**
     * @throws FileOperationException
     * @throws NotWritableException
     */
    public function testWriteHtml(): void {
        $path = implode(DIRECTORY_SEPARATOR, [static::TEST_DIRECTORY, 'a', '0', 'test.html']);

        $dom = new DOMDocument();
        $dom->loadHTML('<!DOCTYPE html><html lang="en"><head><title>dummy</title></head><body><span>dummy text</span></body></html>');
        $file = new File($path);
        $file->writeDomHtml($dom);
        $this->assertEquals(
            "<!DOCTYPE html>\n<html lang=\"en\"><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"><title>dummy</title></head><body><span>dummy text</span></body></html>\n",
            file_get_contents($path)
        );

        unlink(static::TEST_DIRECTORY . '/a/0/test.html');
    }

    /**
     * @throws FileNotFoundException
     * @throws FileOperationException
     */
    public function testCopy(): void {
        $path     = implode(DIRECTORY_SEPARATOR, [static::TEST_DIRECTORY, 'a', 'BASE']);
        $pathCopy = implode(DIRECTORY_SEPARATOR, [static::TEST_DIRECTORY, 'a', 'COPY']);
        $content  = 'this is a file';
        file_put_contents($path, $content);

        $file = new File($path);
        $copy = $file->copy('COPY');
        $this->assertEquals($path, $file->path);
        $this->assertEquals($pathCopy, $copy->path);
        $this->assertEquals($content, file_get_contents($pathCopy));
        unlink($pathCopy);

        $copy = $file->copy($pathCopy);
        $this->assertEquals($path, $file->path);
        $this->assertEquals($pathCopy, $copy->path);
        $this->assertEquals($content, file_get_contents($pathCopy));
        unlink($pathCopy);

        $copy = $file->copy($pathCopy, false);
        $this->assertEquals($path, $file->path);
        $this->assertEquals($pathCopy, $copy->path);
        $this->assertEquals($content, file_get_contents($pathCopy));
        unlink($pathCopy);

        unlink($path);
    }

    /**
     * @throws FileNotFoundException
     */
    public function testDiskSpace(): void {
        $file = new File(static::TEST_DIRECTORY);
        $this->assertEquals(disk_free_space(static::TEST_DIRECTORY), $file->diskFreeSpace());
        $this->assertEquals(disk_total_space(static::TEST_DIRECTORY), $file->diskTotalSpace());
    }

    public function testFnMatch(): void {
        $file = new File(static::TEST_DIRECTORY);
        $this->assertTrue($file->fileNameMatches('test_folder'));
        $this->assertTrue($file->fileNameMatches('test_*'));
        $this->assertFalse($file->fileNameMatches('test_g*'));
    }

    /**
     * @throws FileNotFoundException
     * @throws FileOperationException
     */
    public function testRename(): void {
        $path        = static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'RENAME.ME';
        $renamedPath = static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'RENAMED';

        file_put_contents($path, '');
        $file = new File($path);
        $this->assertTrue($file->isFile());
        $renamed = $file->rename('RENAMED');
        $this->assertTrue($renamed->isFile());
        $this->assertFalse(file_exists($file->path));
        @unlink($path);
        @unlink($renamedPath);

        file_put_contents($path, '');
        $renamed = $file->rename($renamedPath);
        $this->assertTrue($renamed->isFile());
        $this->assertFalse(file_exists($file->path));
        @unlink($path);
        @unlink($renamedPath);

        file_put_contents($path, '');
        $renamed = $file->rename($renamedPath, false);
        $this->assertTrue($renamed->isFile());
        $this->assertFalse(file_exists($file->path));
        @unlink($path);
        @unlink($renamedPath);
    }

    /**
     * @throws FileNotFoundException
     * @throws FileOperationException
     * @throws NotWritableException
     */
    public function testDelete(): void {
        $path         = static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'FILE';
        $folderPath   = static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'FOLDER';
        $folder2Path  = $folderPath . DIRECTORY_SEPARATOR . 'FOLDER2';
        $folder3Path  = $folder2Path . DIRECTORY_SEPARATOR . 'FOLDER3';
        $deepFilePath = $folderPath . DIRECTORY_SEPARATOR . 'FILE';
        @mkdir($folder3Path, 0777, true);
        file_put_contents($path, '');
        file_put_contents($deepFilePath, '');

        try {
            $file = new File($path);
            $file->delete();
            $this->assertFalse(file_exists($path));

            $file = new File($folderPath);
            $file->delete(true);
            $this->assertFalse(file_exists($deepFilePath));
            $this->assertFalse(file_exists($folder3Path));
            $this->assertFalse(file_exists($folder2Path));
            $this->assertFalse(file_exists($folderPath));
        }
        finally {
            @unlink($deepFilePath);
            @unlink($path);

            @rmdir($folder3Path);
            @rmdir($folder2Path);
            @rmdir($folderPath);
        }
    }

    /**
     * @throws FileNotFoundException
     */
    public function testStat(): void {
        $path = static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'FILE';
        file_put_contents($path, 'HELLO!!!');

        $file = new File($path);
        $this->assertIsArray($file->stat());
        $file = new File($path . 'NOPE');
        $this->expectException(FileNotFoundException::class);
        try {
            $file->stat();
        }
        finally {
            @unlink($path);
        }
    }

    /**
     * @throws FileOperationException
     */
    public function testTouch(): void {
        $path        = static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'TOUCH.ME';
        $folder      = static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'TOUCH';
        $dontTouchMe = $folder . DIRECTORY_SEPARATOR . 'ME.TOO';

        @unlink($path);
        @unlink($dontTouchMe);
        @rmdir($folder);

        $file = new File($path);
        $this->assertFalse(file_exists($path));
        $touchedFile = $file->touch();
        $this->assertTrue(file_exists($path));
        $this->assertNotSame($file, $touchedFile);

        @unlink($path);

        $file = new File($dontTouchMe);
        $this->expectException(FileOperationException::class);
        try {
            $file->touch();
        }
        catch (FileOperationException $e) {
            $this->assertEquals(FileOperationException::OPERATION_TOUCH, $e->operation);
            throw $e;
        }
    }

    /**
     * @throws FileOperationException
     * @throws InvalidOperationTargetException
     */
    public function testMakeSubdirectory(): void {
        $file         = new File(static::TEST_DIRECTORY);
        $subDirectory = $file->mkdir('sub');
        $this->assertEquals(static::TEST_DIRECTORY, $file->path);
        $this->assertEquals(static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'sub', $subDirectory->path);
        $this->assertTrue($file->isDir());
        $this->assertTrue($subDirectory->isDir());

        rmdir(static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'sub');
    }

    public function testPathing(): void {
        $composePath = fn (string ...$parts) => implode(DIRECTORY_SEPARATOR, $parts);

        $file = new File();
        $this->assertEquals(getcwd(), $file->path);
        $file = $file->cd(static::TEST_DIRECTORY);
        $this->assertEquals(static::TEST_DIRECTORY, $file->path);
        $file = $file->cd('a');
        $this->assertEquals($composePath(static::TEST_DIRECTORY, 'a'), $file->path);
        $file = $file->cd($composePath('0', 'a'));
        $this->assertEquals($composePath(static::TEST_DIRECTORY, 'a', '0', 'a'), $file->path);
        $file = $file->go($composePath('..', '..'));
        $this->assertEquals($composePath(static::TEST_DIRECTORY, 'a'), $file->path);
        $file = $file->select('..');
        $this->assertEquals(static::TEST_DIRECTORY, $file->path);
        $file = $file->cd($composePath('a', '0', '..', '0', 'a', '..'));
        $this->assertEquals($composePath(static::TEST_DIRECTORY, 'a', '0'), $file->path);

        $cwd = getcwd();
        chdir(__DIR__);
        $this->assertEquals($composePath('.', 'test_folder', 'a', '0'), $file->relativePath());
        chdir($cwd);
    }

    /**
     * @throws FileNotFoundException
     * @throws FileOperationException
     * @throws InvalidOperationTargetException
     */
    public function testLinks(): void {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Cannot test links on Windows');
        }

        $testFile = static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'file.test';
        $name1 = 'another.link.to.file.test';
        $name2 = 'symlink.to.file.test';

        try {
            touch($testFile);
            symlink(static::TEST_DIRECTORY . '/a', static::TEST_DIRECTORY . '/sym');

            $file = new File($testFile);
            $this->assertFalse($file->isLink());
            $file = new File(static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'link.to.file.test');
            $this->assertFalse($file->isLink()); // a hard link is not a link
            $file = new File(static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'sym');
            $this->assertTrue($file->isLink());

            $file = new File(static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'file.test');

            $link = $file->link($name1);
            $this->assertTrue(file_exists(static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . $name1));
            $this->assertEquals(static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . $name1, $link->path);
            $this->assertFalse($link->isLink());

            $symlink = $file->symlink($name2);
            $this->assertTrue(file_exists(static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . $name2));
            $this->assertEquals(static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . $name2, $symlink->path);
            $this->assertTrue($symlink->isLink());
            $this->assertIsArray($symlink->lstat());

            $this->assertIsInt($symlink->linkInfo());
            $this->assertEquals($testFile, $symlink->linkTarget());
        }
        finally {
            @unlink(static::TEST_DIRECTORY . '/sym');
            @unlink(static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . $name1);
            @unlink(static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . $name2);
            @unlink($testFile);
        }
    }

    /**
     * @throws FileNotFoundException
     * @throws CannotChangePermissionException
     */
    public function testChangeOwner(): void {
        if (!function_exists('posix_getuid') || posix_getuid() !== 0) {
            $this->markTestSkipped('Change owner tests only work as root. Don\'t run code you haven\'t properly reviewed as root. Just ignore this test.');
        }

        try {
            $filePath = static::TEST_DIRECTORY . DIRECTORY_SEPARATOR . 'root_data';
            touch($filePath);

            $file = new File($filePath);

            $permissionsChanged = $file->chmod(0777);
            $this->assertEquals(0777, $permissionsChanged->getPerms() & 0777);

            $permissionsChanged = $permissionsChanged->chmod(0644);
            $this->assertEquals(0644, $permissionsChanged->getPerms() & 0644);

            @unlink($filePath);
            touch($filePath);

            $randomId     = 2222;
            $ownerChanged = $file->chown($randomId, $randomId + 1);
            $this->assertEquals($randomId, $ownerChanged->getOwner());
            $this->assertEquals($randomId + 1, $ownerChanged->getGroup());
        } finally {
            @unlink($filePath);
        }
    }

    public static function setUpBeforeClass(): void {
        static::tearDownAfterClass();

        mkdir(static::TEST_DIRECTORY . '/a/0/a', 0755, true);
        mkdir(static::TEST_DIRECTORY . '/a/1', 0755, true);
        mkdir(static::TEST_DIRECTORY . '/b', 0755, true);
    }

    public static function tearDownAfterClass(): void {
        @rmdir(static::TEST_DIRECTORY . '/a/0/a');
        @rmdir(static::TEST_DIRECTORY . '/a/0');
        @rmdir(static::TEST_DIRECTORY . '/a/1');
        @rmdir(static::TEST_DIRECTORY . '/a');
        @rmdir(static::TEST_DIRECTORY . '/b');
    }

    private function assertHasMTime(File $file, bool $has = true) {
        $exception = false;
        try {
            $file->getMTime();
        }
        catch (RuntimeException $e) {
            $exception = true;
        }

        $this->assertNotEquals($has, $exception);
    }

    private function assertDoesntHaveMTime(File $file) {
        $this->assertHasMTime($file, false);
    }

    private function arraysHaveSameContent(array $a, array $b): bool {
        return count($a) === count($b) && empty(array_diff($a, $b));
    }
}
