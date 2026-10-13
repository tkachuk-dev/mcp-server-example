<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Fresh SQLite schema + deterministic fixtures once per run; DAMA wraps each test in a rolled-back transaction.
$kernel = new Kernel('test', false);
$kernel->boot();
$application = new Application($kernel);
$application->setAutoExit(false);

foreach ([
    ['command' => 'doctrine:schema:drop', '--force' => true, '--full-database' => true],
    ['command' => 'doctrine:schema:create'],
    ['command' => 'doctrine:fixtures:load', '--no-interaction' => true],
    ['command' => 'cache:pool:clear', 'pools' => ['cache.rate_limiter']],
] as $command) {
    if (0 !== $application->run(new ArrayInput($command), new NullOutput())) {
        throw new RuntimeException(sprintf('Test bootstrap command "%s" failed.', $command['command']));
    }
}

$kernel->shutdown();
