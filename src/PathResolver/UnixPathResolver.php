<?php

namespace MeesterDev\FileWrapper\PathResolver;

class UnixPathResolver extends AbstractPathResolver {
    public static function isAbsolutePath(string $path): bool {
        return \strncmp($path, static::DIRECTORY_SEPARATOR, \strlen(static::DIRECTORY_SEPARATOR)) === 0;
    }

    public static function resolve(string $base, string $path): string {
        if (static::isAbsolutePath($path)) {
            return static::cleanPath($path);
        }

        return static::cleanPath($base . static::DIRECTORY_SEPARATOR . $path);
    }
}