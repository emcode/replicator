<?php

namespace Replicator\Helper;

use Replicator\Exception\UnreacheablePathException;

class PathHelper
{
    /**
     * @param $somePath
     *
     * @throws UnreacheablePathException
     */
    public function goToDir($somePath)
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
    public function getPathDiff($baseDir, $otherDir)
    {
        if (strpos($otherDir, $baseDir) === 0)
        {
            $otherDir = mb_substr($otherDir, strlen($baseDir));

            return self::normalizePath($otherDir);
        }

        return $otherDir;
    }

    /**
     * @param string $somePath
     *
     * @return string
     */
    public static function normalizePath($somePath)
    {
        return trim($somePath, '/');
    }
}
