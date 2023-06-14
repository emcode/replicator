<?php

namespace Replicator\Command;

use Assert\Assertion;
use Replicator\Helper\PathHelper;
use Simplercode\GAL\Command\RemoteCommand;
use Simplercode\GAL\Processor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateMirrorCommand extends Command
{
    protected string $workingDir;

    protected PathHelper $pathHelper;

    protected string $lastWorkingDir;

    /**
     * MirrorCommand constructor.
     */
    public function __construct(
        string $workingDir,
        PathHelper $pathHelper,
        ?string $name = null
    )
    {
        $this->workingDir = $workingDir;
        $this->pathHelper = $pathHelper;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('mirror:update')
            ->setDescription('Updates one or many GIT mirror repositories using "git remote update" command')
            ->addArgument(
                'mirror-name',
                InputArgument::OPTIONAL,
                'Name of mirror repository to update (can also contain subdirectories). ' .
                'If argument is not present current working directory or directory from config will be used instead.'
            )
            ->addOption(
                '--all',
                '-a',
                InputOption::VALUE_NONE,
                'Search recursively and update all repositories within path passed <mirror-name> argument'
            )
            ->addOption(
                '--prune',
                '-p',
                InputOption::VALUE_NONE,
                'Pass --prune flag to GIT (removes locally branches deleted in mirrored repository)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mirrorName = $input->getArgument('mirror-name');

        if (null === $mirrorName)
        {
            // assume that repo is in our workingDir: workingDir can come from getcwd() or from config
            $mirrorName = $this->workingDir;
        } else
        {
            Assertion::string($mirrorName);
        }

        $mirrorName = $this->pathHelper->normalizePath($mirrorName);

        if ($output->isVerbose())
        {
            $output->writeln(sprintf('Working directory: <info>%s</info>', $this->workingDir));
        }

        $lastWorkingDir = getcwd();
        Assertion::string($lastWorkingDir);

        $this->pathHelper->goToDir($this->workingDir);

        $all = $input->getOption('all');

        $updateArguments = ['update'];

        if ($input->getOption('prune'))
        {
            $updateArguments[] = '--prune';
        }

        if (true === $all)
        {
            $output->writeln('Searching for bare repositories, please wait...');
            $repositories = $this->searchForBareRepositories($this->workingDir);
            $repositoriesNum = count($repositories);

            $output->writeln(sprintf('Found <info>%s</info> repositories', $repositoriesNum));

            if (empty($repositories))
            {
                $output->writeln(sprintf('<comment>There are no GIT bare repositories in path: %s</comment>', $this->workingDir));
                $output->writeln(sprintf('<comment>Nothing to update.</comment>'));
                $output->writeln('<info>Command complete.</info>');
                $this->pathHelper->goToDir($lastWorkingDir);

                return self::INVALID;
            }

            $processor = new Processor();
            $remoteCommand = new RemoteCommand($processor);

            $index = 1;

            foreach($repositories as $repositoryPath)
            {
                $niceRepoName = $this->pathHelper->getPathDiff($this->workingDir, $repositoryPath);
                $output->writeln(sprintf('Updating repository: <info>%s</info> (%s of %s)', $niceRepoName, $index, $repositoriesNum));
                $output->writeln('Please wait...');
                $processor->setPathToRepo($repositoryPath, true);
                $remoteCommand->execute($updateArguments);
                ++$index;
            }

            $output->writeln('<info>Command complete.</info>');

            return self::SUCCESS;
        }

        $repositoryPath = ($mirrorName === $this->workingDir) ? $mirrorName : $this->workingDir . DIRECTORY_SEPARATOR . $mirrorName;

        if (!$this->doesLookLikeBareGitRepository($repositoryPath))
        {
            $output->writeln(sprintf('<comment>There are no GIT bare repository in path: %s</comment>', $repositoryPath));
            $output->writeln(sprintf('<comment>Nothing to update.</comment>'));
            $output->writeln('<info>Command complete.</info>');
            $this->pathHelper->goToDir($lastWorkingDir);

            return self::INVALID;
        }

        $processor = new Processor();
        $remoteCommand = new RemoteCommand($processor);

        $output->writeln(sprintf('Updating repository: <info>%s</info>', $mirrorName));
        $output->writeln('Please wait...');
        $processor->setPathToRepo($repositoryPath, true);
        $remoteCommand->execute($updateArguments);

        $output->writeln('<info>Command complete.</info>');
        $this->pathHelper->goToDir($lastWorkingDir);

        return self::SUCCESS;
    }

    /**
     * @return array|string[]
     */
    public function searchForBareRepositories(string $dirToSearch): array
    {
        $dirIterator = new \RecursiveDirectoryIterator($dirToSearch);
        $pathIterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);

        $repoPaths = [];

        foreach($pathIterator as $name => $object)
        {
            /** @var \SplFileInfo $object */
            $currentPath = $object->getPath();

            if (in_array($currentPath, $repoPaths, true))
            {
                continue;
            }

            if ($object->isFile() && $object->getBasename() === 'HEAD')
            {
                if ($this->doesLookLikeBareGitRepository($currentPath))
                {
                    $repoPaths[] = $currentPath;
                }
            }
        }

        return $repoPaths;
    }

    /**
     * @param string $somePath
     *
     * @return bool
     */
    public function doesLookLikeBareGitRepository($somePath)
    {
        $pathSections = explode(DIRECTORY_SEPARATOR, $somePath);
        $lastSection = end($pathSections);

        if ($lastSection === '.git')
        {
            return false;
        }

        return is_file($somePath . DIRECTORY_SEPARATOR . 'HEAD')
            && is_file($somePath . DIRECTORY_SEPARATOR . 'config')
            && is_dir($somePath . DIRECTORY_SEPARATOR . 'objects')
            && is_dir($somePath . DIRECTORY_SEPARATOR . 'refs');
    }
}
