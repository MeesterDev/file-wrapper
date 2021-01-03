<?php

use MeesterDev\FileWrapper\PathResolver\UnixPathResolver;
use MeesterDev\FileWrapper\PathResolver\WindowsPathResolver;
use PHPUnit\Framework\TestCase;

class PathResolverTest extends TestCase {
    public function testWindowsPartResolver() {
        $resolver = new WindowsPathResolver();

        $this->assertTrue($resolver::isAbsolutePath('C:\\file'));
        $this->assertTrue($resolver::isAbsolutePath('D:\\file'));
        $this->assertTrue($resolver::isAbsolutePath('D:'));
        $this->assertTrue($resolver::isAbsolutePath('D:\\folder\\file'));

        $this->assertFalse($resolver::isAbsolutePath('D'));
        $this->assertFalse($resolver::isAbsolutePath('.\\test'));
        $this->assertFalse($resolver::isAbsolutePath('test'));
        $this->assertFalse($resolver::isAbsolutePath('test\\test'));
        $this->assertFalse($resolver::isAbsolutePath('\\test'));
        $this->assertFalse($resolver::isAbsolutePath('test\\'));
        $this->assertFalse($resolver::isAbsolutePath('\\'));
        $this->assertFalse($resolver::isAbsolutePath('NotADrive:\\'));
        $this->assertFalse($resolver::isAbsolutePath('_:\\'));

        $this->assertEquals('D:\\test', $resolver::resolve('C:', 'D:\\test'));
        $this->assertEquals('C:\\test', $resolver::resolve('C:', '\\test'));
        $this->assertEquals('C:\\test\\test', $resolver::resolve('C:\\test', 'test'));
        $this->assertEquals('D:\\test4', $resolver::resolve('C:\\test\\test2', 'D:\\test4'));
        $this->assertEquals('D:\\test3', $resolver::resolve('C:\\test\\test2', 'D:\\test4\\..\\test3'));
        $this->assertEquals('E:\\test\\test2\\test3', $resolver::resolve('E:\\test\\test2', 'test4\\..\\test3'));

        $abc = 'C:\\a\\b\\c';
        $this->assertEquals('..\\d', $resolver::relativePath($abc, 'C:\\a\\b\\d'));
        $this->assertEquals('..\\..\\d\\e', $resolver::relativePath($abc, 'C:\\a\\d\\e'));
        $this->assertEquals('..\\..\\d', $resolver::relativePath($abc, 'C:\\a\\d'));
        $this->assertEquals('..\\..', $resolver::relativePath($abc, 'C:\\a'));
        $this->assertEquals('.\\d', $resolver::relativePath($abc, 'C:\\a\\b\\c\\d'));
        $this->assertEquals('.\\d\\e', $resolver::relativePath($abc, 'C:\\a\\b\\c\\d\\e\\'));

        $this->assertEquals('D:\\a\\b\\c\\d\\e\\', $resolver::relativePath($abc, 'D:\\a\\b\\c\\d\\e\\'));
        $this->assertEquals('E:\\a\\b\\c', $resolver::relativePath($abc, 'E:\\a\\b\\c'));
    }

    public function testUnixPartResolver() {
        $resolver = new UnixPathResolver();

        $this->assertTrue($resolver::isAbsolutePath('/file'));
        $this->assertTrue($resolver::isAbsolutePath('/'));
        $this->assertTrue($resolver::isAbsolutePath('/folder/file'));

        $this->assertFalse($resolver::isAbsolutePath('D'));
        $this->assertFalse($resolver::isAbsolutePath('./test'));
        $this->assertFalse($resolver::isAbsolutePath('test'));
        $this->assertFalse($resolver::isAbsolutePath('test/test'));
        $this->assertFalse($resolver::isAbsolutePath('test/'));

        $this->assertEquals('/mnt/d/test', $resolver::resolve('/mnt/c', '/mnt/d/test'));
        $this->assertEquals('/mnt/c/test', $resolver::resolve('/mnt/c', 'test'));
        $this->assertEquals('/mnt/c/test/test', $resolver::resolve('/mnt/c/test', 'test'));
        $this->assertEquals('/mnt/d/test4', $resolver::resolve('/mnt/c/test/test2', '/mnt/d/test4'));
        $this->assertEquals('/mnt/d/test3', $resolver::resolve('/mnt/c/test/test2', '/mnt/d/test4/../test3'));
        $this->assertEquals('/mnt/e/test/test2/test3', $resolver::resolve('/mnt/e/test/test2', 'test4/../test3'));

        $abc = '/mnt/c/a/b/c';
        $this->assertEquals('../d', $resolver::relativePath($abc, '/mnt/c/a/b/d'));
        $this->assertEquals('../../d/e', $resolver::relativePath($abc, '/mnt/c/a/d/e'));
        $this->assertEquals('../../d', $resolver::relativePath($abc, '/mnt/c/a/d'));
        $this->assertEquals('../..', $resolver::relativePath($abc, '/mnt/c/a'));
        $this->assertEquals('./d', $resolver::relativePath($abc, '/mnt/c/a/b/c/d'));
        $this->assertEquals('./d/e', $resolver::relativePath($abc, '/mnt/c/a/b/c/d/e/'));
    }
}
