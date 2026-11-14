<?php

// Usa exactamente el mismo aislamiento que el servidor de demostración.
$app = require __DIR__.'/bootstrap.php';
exit($app->handleCommand(new Symfony\Component\Console\Input\ArgvInput));
