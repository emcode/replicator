<?php

namespace Replicator\Helper;

class NamingHelper
{
    /**
     * @var string
     */
    protected $repositoryPattern = '/source[ ]+: \[git\] (?P<repository>[^ ]+)/';

    /**
     * @var string
     */
    protected $packagePattern = '/^[a-zA-Z0-9-]+\/[a-zA-Z0-9-]+$/';
    
    /**
     * @param $repoToClone
     * @param null $targetRepoName
     * @param null $parentPath
     * @return string
     */
    public function chooseTargetRepositoryPath($repoToClone, $targetRepoName = null, $parentPath = null)
    {
        if (null !== $parentPath)
        {
            $parentPath = PathHelper::normalizePath($parentPath);
        }

        if (null !== $targetRepoName)
        {
            $targetRepoName = PathHelper::normalizePath($targetRepoName);
        }

        if (null === $parentPath)
        {
            if (null === $targetRepoName)
            {
                $targetPath = $this->prepareTargetRepositoryPath($repoToClone, true);
                
            } else
            {
                $targetPath = $targetRepoName;
            }

        } else
        {
            // we have some parent path that we should use
            
            if (null === $targetRepoName)
            {
                $mirrorRepoName = $this->prepareTargetRepositoryPath($repoToClone, false);
                $targetPath = $parentPath . DIRECTORY_SEPARATOR . $mirrorRepoName;
                
            } else
            {
                $targetPath = $parentPath . DIRECTORY_SEPARATOR . $targetRepoName;
            }
        }
        
        return $targetPath;
    }

    /**
     * @param string $repoPathOrUrl
     * @param bool $withParentPath
     * @return string
     */
    public function prepareTargetRepositoryPath($repoPathOrUrl, $withParentPath = false)
    {
        $path = parse_url($repoPathOrUrl, PHP_URL_PATH);
        $pathSections = explode(DIRECTORY_SEPARATOR, $path);

        $repoName = array_pop($pathSections);
        $repoPath = rtrim($repoName, '.git');

        if ($withParentPath)
        {
            if (empty($pathSections))
            {
                throw new \InvalidArgumentException(
                    'Could not determine repository path from original value "%s". ' .
                    'Please define repository prefix explicitly using --prefix flag'
                );
            }

            $parentPath = array_pop($pathSections);
            $repoPath = $parentPath . DIRECTORY_SEPARATOR . $repoPath;
        }

        return $repoPath;
    }
    
    /**
     * @param array $composerOutputLines
     *
     * @return string|null
     */
    public function findPackageRepositoryInComposerOutput(array $composerOutputLines)
    {
        $repository = null;

        foreach($composerOutputLines as $rawLine)
        {
            if (strpos($rawLine, 'source') === false)
            {
                continue;
            }

            $matches = [];
            $matchingResult = preg_match($this->repositoryPattern, $rawLine, $matches);

            if (1 !== $matchingResult)
            {
                continue;
            }

            $repository = $matches['repository'];
        }

        return $repository;
    }

    /**
     * @param array $composerOutputLines
     * 
     * @return array
     */
    public function extractPackageNamesFromComposerOutput(array $composerOutputLines)
    {
        $packageNames = [];

        foreach($composerOutputLines as $rawLine)
        {
            if (empty($rawLine))
            {
                continue;
            }

            $packageName = trim($rawLine);

            $matchingResult = preg_match($this->packagePattern, $packageName);

            if (1 !== $matchingResult)
            {
                continue;
            }

            $packageNames[] = $packageName;
        }

        return $packageNames;
    }
}