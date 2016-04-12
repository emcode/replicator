<?php

namespace Replicator\Command;

use Replicator\Exception\FileNotFoundException;
use Replicator\Exception\UnreacheablePathException;
use Replicator\Helper\NamingHelper;
use Replicator\Helper\PathHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Console\Helper\Table;

class ReplicateCommand extends Command
{
    /**
     * @var string
     */
    protected $workingPath;
    
    /**
     * @var PathHelper
     */
    protected $pathHelper;

    /**
     * @var NamingHelper
     */
    protected $namingHelper;
    
    /**
     * MirrorCommand constructor.
     *
     * @param string $workingPath
     * @param NamingHelper $namingHelper
     * @param PathHelper $pathHelper
     * @param string|null $name
     */
    public function __construct($workingPath, NamingHelper $namingHelper, PathHelper $pathHelper, $name = null)
    {
        $this->workingPath = $workingPath;
        $this->pathHelper = $pathHelper;
        $this->namingHelper = $namingHelper;
        parent::__construct($name);
    }
    
    protected function configure()
    {
        $this
            ->setName('replicate')
            ->setDescription('Mirrors all composer dependencies of given project')
            ->addArgument(
                'project-path',
                InputArgument::REQUIRED,
                'Path that contains composer.json file'
            )
            ->addOption(
                '--dry-run',
                '-d',
                InputOption::VALUE_NONE,
                'Analyse the project but do not create any mirrors'
            )
            ->addOption(
                '--parent-path',
                '-p',
                InputOption::VALUE_REQUIRED,
                'Path in which mirror repositories will be created (inside working directory)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectPath = $input->getArgument('project-path');

        $lastWorkingDir = getcwd();
        $this->pathHelper->goToDir($projectPath);

        if (!is_readable('./composer.json'))
        {
            throw new FileNotFoundException(sprintf('Could not find composer.json file in path: %s', getcwd()));
        }

        $builder = $this->setupComposerCommandBuilder();
        $builder->setArguments([
            'show',
            '--name-only',
        ]);

        $process = $builder->getProcess();
        $process->mustRun();
        $processOutput = $process->getOutput();
        $rawLines = explode(PHP_EOL, $processOutput);
        $packageNames = $this->namingHelper->extractPackageNamesFromComposerOutput($rawLines);

        if ($output->isVerbose())
        {
            $output->writeln(sprintf('Found <info>%s</info> vendor packages', count($packageNames)));
        }

        if (empty($packageNames))
        {
            $output->writeln(sprintf('<comment>Could not find any vendor packages inside this project</comment>'));
            $output->writeln('<info>Command complete.</info>');

            return;
        }

        $debugTableContent = [];
        $mirrorsToCreate = [];

        foreach($packageNames as $currentPackage)
        {
            if ($output->isVerbose())
            {
                $output->writeln(sprintf('Finding repository for package: <info>%s</info>', $currentPackage));
            }

            $builder->setArguments([
                'show',
                $currentPackage,
            ]);

            $process = $builder->getProcess();
            $process->mustRun();
            $processOutput = $process->getOutput();
            $rawLines = explode(PHP_EOL, $processOutput);
            $packageRepository = $this->namingHelper->findPackageRepositoryInComposerOutput($rawLines);

            if (null === $packageRepository)
            {
                $debugTableContent[] = [$currentPackage, '--- MISSING ---'];
                $output->writeln(sprintf('<comment>Could not find repository URL or path for package: %s</comment>', $currentPackage));
                $output->writeln(sprintf('<comment>Package %s will be ignored during mirroring</comment>', $currentPackage));
                continue;
            }

            $debugTableContent[] = [$currentPackage, $packageRepository];
            $mirrorsToCreate[$currentPackage] = $packageRepository;
        }

        $output->writeln(sprintf('<info>Results of project dependency analysis</info>'));

        $table = new Table($output);
        $table
            ->setHeaders(array('package', 'repository'))
            ->setRows($debugTableContent)
        ;

        $table->render();

        if (empty($mirrorsToCreate))
        {
            $output->writeln(sprintf('<comment>Could not find any repositories for packages inside this project</comment>'));
            $output->writeln('<info>Command complete.</info>');
            $this->pathHelper->goToDir($lastWorkingDir);

            return;
        }

        $questionHelper = $this->getHelper('question');
        $question = new ConfirmationQuestion(sprintf('Create mirror repositories of <info>%s</info> projects? [yes] ', count($mirrorsToCreate)), true);

        if (!$questionHelper->ask($input, $output, $question))
        {
            $this->pathHelper->goToDir($lastWorkingDir);

            return;
        }

        $parentPath = $input->getOption('parent-path');
        $mirrorsCount = count($mirrorsToCreate);
        $mirrorCommand = new CreateMirrorCommand($lastWorkingDir, $this->namingHelper, $this->pathHelper);
        $index = 1;

        foreach($mirrorsToCreate as $package => $repository)
        {
            $output->writeln(sprintf(
                'Creating mirror for package <info>%s</info> (%s of %s)', $package, $index, $mirrorsCount
            ));

            $arguments = [
                'repository' => $repository,
                '--target-name' => $package,
                '--parent-path' => $parentPath,
            ];

            $mirrorCommandInput = new ArrayInput($arguments);
            $returnCode = $mirrorCommand->run($mirrorCommandInput, $output);

            if (0 !== $returnCode)
            {
                $output->writeln('<error>Received return code other than 0 from mirror creation command</error>');
            }

            ++$index;
        }

        $this->pathHelper->goToDir($lastWorkingDir);
        $output->writeln('<info>Command complete.</info>');
    }
    
    /**
     * @return ProcessBuilder
     */
    protected function setupComposerCommandBuilder()
    {
        $builder = new ProcessBuilder();

        if (is_file('./composer.phar'))
        {
            $builder->setPrefix('php ./composer.phar');

        } else
        {
            $builder->setPrefix('composer');
        }

        return $builder;
    }
}
