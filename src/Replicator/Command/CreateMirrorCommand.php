<?php

namespace Replicator\Command;

use Assert\Assertion;
use Replicator\Helper\NamingHelper;
use Replicator\Helper\PathHelper;
use Simplercode\GAL\Command\CloneCommand;
use Simplercode\GAL\Processor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateMirrorCommand extends Command
{
    /**
     * @var string
     */
    protected $workingPath;

    /**
     * @var NamingHelper
     */
    protected $namingHelper;

    /**
     * @var PathHelper
     */
    protected $pathHelper;

    /**
     * MirrorCommand constructor.
     *
     * @param string       $workingPath
     * @param NamingHelper $namingHelper
     * @param PathHelper   $pathHelper
     * @param string|null  $name
     */
    public function __construct($workingPath, NamingHelper $namingHelper, PathHelper $pathHelper, $name = null)
    {
        $this->workingPath = $workingPath;
        $this->namingHelper = $namingHelper;
        $this->pathHelper = $pathHelper;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('mirror:create')
            ->setDescription('Mirrors GIT repository using "git clone --bare --mirror" command')
            ->addArgument(
                'repository',
                InputArgument::REQUIRED,
                'URL or path to GIT repository that you want to mirror'
            )
            ->addOption(
                '--mirror-name',
                '-m',
                InputOption::VALUE_REQUIRED,
                'Target name of mirror repository (can also contain subdirectories)'
            )
            ->addOption(
                '--parent-path',
                '-p',
                InputOption::VALUE_REQUIRED,
                'Path in which mirror repository will be created (inside working directory)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repoToClone = $input->getArgument('repository');
        Assertion::string($repoToClone);

        $targetRepoName = $input->getOption('mirror-name');
        Assertion::nullOrString($targetRepoName);
        /** @var string|null $targetRepoName */

        $parentPath = $input->getOption('parent-path');
        Assertion::nullOrString($parentPath);
        /** @var string|null $parentPath */

        $targetPath = $this->namingHelper->chooseTargetRepositoryPath($repoToClone, $targetRepoName, $parentPath);

        if ($output->isVerbose())
        {
            $output->writeln(sprintf('Working directory: <info>%s</info>', $this->workingPath));
        }

        $output->writeln(sprintf('Cloning <info>%s</info> to <info>%s</info>', $repoToClone, $targetPath));

        $lastWorkingDir = getcwd();
        Assertion::string($lastWorkingDir);

        $this->pathHelper->goToDir($this->workingPath);
        $output->writeln('Cloning, please wait...');
        $cloneCommand = new CloneCommand(new Processor());
        $cloneCommand->execute([$repoToClone, $targetPath, '--mirror', '--bare']);
        $this->pathHelper->goToDir($lastWorkingDir);
        return self::SUCCESS;
    }
}
