<?php

namespace Replicator\Helper;

use Replicator\Exception\UnreacheablePathException;

class PathHelper
{
    /**
     * @throws UnreacheablePathException
     */
    public function goToDir(string $somePath): void
    {
        $changingResult = chdir($somePath);

        if (true !== $changingResult)
        {
            throw new UnreacheablePathException(sprintf('Could not change working dir to path: %s', $somePath));
        }
    }

    /**
     * @param string $baseDir
     * @param string $otherDir
     * 
     * @return string
     */
    public function getPathDiff(string $baseDir, string $otherDir): string
    {
        if (strpos($otherDir, $baseDir) === 0)
        {
            $otherDir = mb_substr($otherDir, strlen($baseDir));

            return self::normalizePath($otherDir);
        }

        return $otherDir;
    }

    public static function normalizePath(string $somePath): string
    {
        return trim($somePath, '/');
    }
}
