<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Replicator\Command\CreateMirrorCommand;
use Replicator\Command\UpdateMirrorCommand;
use Replicator\Command\ReplicateCommand;
use Replicator\Helper\PathHelper;
use Replicator\Helper\NamingHelper;

$workingDir = getcwd();
$pathHelper = new PathHelper();
$namingHelper = new NamingHelper();

$app = new Application();
$app->add(new CreateMirrorCommand($workingDir, $namingHelper, $pathHelper));
$app->add(new UpdateMirrorCommand($workingDir, $pathHelper));
$app->add(new ReplicateCommand($workingDir, $namingHelper, $pathHelper));
$app->run();