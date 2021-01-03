<?php

namespace MeesterDev\FileWrapper\PathResolver;

class WindowsPathResolver extends AbstractPathResolver {
    protected const DIRECTORY_SEPARATOR = '\\';

    public static function isAbsolutePath(string $path): bool {
        $drive = static::getDrive($path);
        return $drive !== null && \preg_match('/[A-Z]+:/', $drive) === 1;
    }

    protected static function getDrive(string $path): ?string {
        $position = \strpos($path, ':');

        if ($position === false) {
            return null;
        }

        return \substr($path, 0, $position + 1);
    }

    public static function resolve(string $base, string $path): string {
        if (!static::isAbsolutePath($path)) {
            $path = $base . static::DIRECTORY_SEPARATOR . $path;
        }

        $parts = static::splitPath($path);
        $drive = \array_splice($parts, 0, 1);
        $parts = static::cleanPathParts($parts);

        return $drive[0] . static::DIRECTORY_SEPARATOR . \implode(static::DIRECTORY_SEPARATOR, $parts);
    }

    public static function relativePath(string $base, string $path, bool $force = false): string {
        if (static::getDrive($path) !== static::getDrive($base)) {
            return $path;
        }

        return parent::relativePath($base, $path, $force);
    }
}