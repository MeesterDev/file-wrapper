<?php

namespace MeesterDev\FileWrapper\PathResolver;

abstract class AbstractPathResolver {
    protected const DIRECTORY_SEPARATOR = '/';

    public static abstract function isAbsolutePath(string $path): bool;
    public static abstract function resolve(string $base, string $path): string;

    /**
     * @param string $base
     * @param string $path
     * @param bool   $force force a relative path if possible. On Windows this isn't possible if the drives of the paths are different.
     *
     * @return string
     */
    public static function relativePath(string $base, string $path, bool $force = false): string {
        $partsBase = \explode(static::DIRECTORY_SEPARATOR, \trim($base, static::DIRECTORY_SEPARATOR));
        $partsFile = \explode(static::DIRECTORY_SEPARATOR, \trim($path, static::DIRECTORY_SEPARATOR));
        $n         = \min(count($partsBase), \count($partsFile));

        for ($i = 0; $i < $n; $i++) {
            if ($partsBase[$i] != $partsFile[$i]) {
                break;
            }
        }

        if ($i === 0 && !$force) {
            // no use in using a relative path if the first directory already differs
            return $path;
        }

        $remainingBaseParts = \count($partsBase) - $i;
        \array_splice($partsFile, 0, $i);

        $result = \rtrim(\str_repeat('..' . static::DIRECTORY_SEPARATOR, $remainingBaseParts) . \implode(static::DIRECTORY_SEPARATOR, $partsFile), static::DIRECTORY_SEPARATOR);
        if ($result === '') {
            return '.';
        }
        if ($remainingBaseParts === 0) {
            return '.' . static::DIRECTORY_SEPARATOR . $result;
        }

        return $result;
    }

    protected static function cleanPath(string $path): string {
        return static::joinPath(static::cleanPathParts(static::splitPath($path)));
    }

    protected static function splitPath(string $path): array {
        $escapedSeparator = \preg_quote(static::DIRECTORY_SEPARATOR, '#');

        return \preg_split("#$escapedSeparator+#", $path);
    }

    protected static function joinPath(array $parts): string {
        return implode(static::DIRECTORY_SEPARATOR, $parts);
    }

    protected static function cleanPathParts(array $parts): array {
        for ($i = 0; $i < count($parts); $i++) {
            if ($parts[$i] === '.' || ($i === 0 && $parts[0] === '..')) {
                \array_splice($parts, $i, 1);
                $i--;
            }
            elseif ($parts[$i] === '..') {
                \array_splice($parts, $i - 1, 2);
                $i -= 2;
            }
        }

        return $parts;
    }
}