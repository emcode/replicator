<?php

namespace Replicator\Command;

use Replicator\Exception\UnreacheablePathException;
use Simplercode\GAL\Command\CloneCommand;
use Simplercode\GAL\Processor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MirrorCommand extends Command
{
    /**
     * @var string
     */
    protected $workingPath;

    /**
     * MirrorCommand constructor.
     * 
     * @param string      $workingPath
     * @param string|null $name
     */
    public function __construct($workingPath, $name = null)
    {
        $this->workingPath = $workingPath;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('mirror')
            ->setDescription('Mirrors GIT repository using --bare --mirror flags')
            ->addArgument(
                'repository',
                InputArgument::REQUIRED,
                'URL or path to GIT repository that you want to mirror'
            )
            ->addOption(
                '--parent-path',
                '-p',
                InputOption::VALUE_REQUIRED,
                'Path in which mirror repository will be created (inside working directory)'
            )
            ->addOption(
                '--target-name',
                '-t',
                InputOption::VALUE_REQUIRED,
                'Target name of mirror repository (can also contain subdirectories)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repoToClone = $input->getArgument('repository');
        $parentPath = $input->getOption('parent-path');
        $targetRepoName = $input->getOption('target-name');

        if (null !== $parentPath)
        {
            $parentPath = trim($parentPath, '/');
        }

        if (null !== $targetRepoName)
        {
            $targetRepoName = trim($targetRepoName, '/');
        }

        if (null !== $parentPath)
        {
            if (null !== $targetRepoName)
            {
                $targetPath = $parentPath . DIRECTORY_SEPARATOR . $targetRepoName;

            } else
            {
                $mirrorRepoName = $this->prepareTargetRepositoryPath($repoToClone, false);
                $targetPath = $parentPath . DIRECTORY_SEPARATOR . $mirrorRepoName;
            }

        } else
        {
            if (null !== $targetRepoName)
            {
                $targetPath = $targetRepoName;

            } else
            {
                $targetPath = $this->prepareTargetRepositoryPath($repoToClone, true);
            }
        }

        if ($output->isVerbose())
        {
            $output->writeln(sprintf('Working directory: <info>%s</info>', $this->workingPath));
        }

        $output->writeln(sprintf('Cloning <info>%s</info> to <info>%s</info>', $repoToClone, $targetPath));

        $lastWorkingDir = getcwd();
        $changingResult = chdir($this->workingPath);

        if (true !== $changingResult)
        {
            throw new UnreacheablePathException(sprintf('Could not change working dir to: %s', $this->workingPath));
        }

        $output->writeln('Cloning, please wait...');
        $cloneCommand = new CloneCommand(new Processor());
        $cloneCommand->execute([$repoToClone, $targetPath, '--mirror', '--bare']);

        $changingResult = chdir($lastWorkingDir);

        if (true !== $changingResult)
        {
            throw new UnreacheablePathException(sprintf('Could not change working dir back to original path: %s', $lastWorkingDir));
        }

        $output->writeln('Cloning complete.');
    }

    protected function prepareTargetRepositoryPath($repoPathOrUrl, $withParentPath = false)
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
}
