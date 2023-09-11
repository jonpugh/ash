#!/usr/bin/env php
<?php

/**
 * ash shell script.
 * Borrowed from the old 'alias-tool': https://github.com/consolidation/site-alias/blob/3.0.0/alias-tool
 * Example commandline front controller
 *
 * The commandline tool is useful for providing ad-hoc access to our class implementations
 */

// If we're running from phar load the phar autoload file.
$pharPath = \Phar::running(true);
if ($pharPath) {
    $autoloaderPath = "$pharPath/vendor/autoload.php";
} else {
    if (file_exists(__DIR__.'/vendor/autoload.php')) {
        $autoloaderPath = __DIR__.'/vendor/autoload.php';
    } elseif (file_exists(__DIR__.'/../../autoload.php')) {
        $autoloaderPath = __DIR__ . '/../../autoload.php';
    } else {
        die("Could not find autoloader. Run 'composer install'.");
    }
}
$classLoader = require $autoloaderPath;

// Customization variables
$argv = $_SERVER['argv'];
$appName = "SiteAlias";
$appVersion = trim(file_get_contents(__DIR__ . '/VERSION'));
$commandClasses = [ \Consolidation\SiteAlias\Cli\SiteAliasCommands::class ];
$selfUpdateRepository = 'consolidation/site-alias';
$configPrefix = 'SITEALIAS';
$configFilePath = getenv($configPrefix . '_CONFIG') ?: getenv('HOME') . '/.site-alias/site-alias.yml';

// Define our Runner, and pass it the command classes we provide.
$runner = new \Robo\Runner($commandClasses);
$runner
  ->setSelfUpdateRepository($selfUpdateRepository)
  ->setConfigurationFilename($configFilePath)
  ->setEnvConfigPrefix($configPrefix)
  ->setClassLoader($classLoader);

// Execute the command and return the result.
$output = new \Symfony\Component\Console\Output\ConsoleOutput();
$statusCode = $runner->execute($argv, $appName, $appVersion, $output);
exit($statusCode);