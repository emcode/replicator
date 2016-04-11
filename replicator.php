<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Replicator\Command\MirrorCommand;
use Replicator\Command\ReplicateCommand;

$workingDir = getcwd();

$app = new Application();
$app->add(new MirrorCommand($workingDir));
$app->add(new ReplicateCommand());
$app->run();