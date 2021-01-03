<?php

namespace MeesterDev\FileWrapper\PathResolver;

class PathResolverFactory {
    /** @var AbstractPathResolver[] */

    private static array $instances = [];

    public static function getPathResolver(?string $fileSystemType = null): AbstractPathResolver {
        if ($fileSystemType === null) {
            $fileSystemType = PHP_OS_FAMILY;
        }

        if (!isset(static::$instances[$fileSystemType])) {
            static::$instances[$fileSystemType] = static::getInstanceFor($fileSystemType);
        }

        return static::$instances[$fileSystemType];
    }

    private static function getInstanceFor(?string $fileSystemType): AbstractPathResolver {
        if ($fileSystemType === 'Windows') {
            return new WindowsPathResolver();
        }

        return new UnixPathResolver();
    }
}