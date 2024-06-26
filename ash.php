<?php

/**
 * This script runs ash.
 *
 * ## Responsibilities of this script ##
 *
 *   - Include the Composer autoload file.
 *   - Set up the environment (record user home directory, cwd, etc.).
 *   - Call the Preflight object to do all necessary setup and execution.
 *   - Exit with status code returned
 *
 * The Ash bootstrap goes through the following steps:
 *
 *   - (ArgsPreprocessor) Preprocess the commandline arguments, considering only:
 *     - The named alias `@sitealias` (removed from arguments if present)
 *     - The --root option (read and retained)
 *     - The --config option (read and retained)
 *     - The --alias-path option (read and retained)
 *   - Load the Ash configuration and alias files from the standard
 *     global locations (including --config and --alias-path)
 *   - Determine the local Drupal site targeted, if any
 *
 */

// We use PWD if available because getcwd() resolves symlinks, which  could take
// us outside of the Drupal root, making it impossible to find. In addition,
// is_dir() is used as the provided path may not be recognizable by PHP. For
// instance, Cygwin adds a '/cygdrive' prefix to the path which is a virtual
// directory.
$cwd = isset($_SERVER['PWD']) && is_dir($_SERVER['PWD']) ? $_SERVER['PWD'] : getcwd();

$autoloadFile = FALSE;
// Set up autoloader
$candidates = [
    $_composer_autoload_path ?? __DIR__ . '/../vendor/autoload.php', // https://getcomposer.org/doc/articles/vendor-binaries.md#finding-the-composer-autoloader-from-a-binary
    dirname(__DIR__, 2) . '/autoload.php', // Needed for \Drush\TestTraits\DrushTestTrait::getPathToDrush
    __DIR__ . '/vendor/autoload.php', // For development of Ash itself.
];
foreach ($candidates as $candidate) {
    if (file_exists($candidate)) {
        $autoloadFile = $candidate;
        break;
    }
}
if (!$autoloadFile) {
    throw new \Exception("Could not locate autoload.php. cwd is $cwd; __DIR__ is " . __DIR__);
}
$loader = include $autoloadFile;
if (!$loader) {
    throw new \Exception("Invalid autoloadfile: $autoloadFile. cwd is $cwd; __DIR__ is " . __DIR__);
}

// Customization variables
$argv = $_SERVER['argv'];
$appName = "Alias Shell";
$appVersion = trim(file_get_contents(__DIR__ . '/VERSION'));

// Load command files.
// @TODO: Load command files from chosen alias.
$discovery = new \Consolidation\AnnotatedCommand\CommandFileDiscovery();
$discovery->setSearchPattern('*Commands.php');

// Current directory.
$directoryList[] = $cwd . '/ash/Commands';

// Home directory
$directoryList[] = getenv('HOME') . '/.ash/Commands';

// Internal commands.
$directoryList[] = __DIR__ . '/src/Commands';
$commandClasses = $discovery->discover($directoryList, '\Ash\Commands');

// Include all files.
foreach ($commandClasses as $file => $class) {
  include $file;
}

$selfUpdateRepository = 'jonpugh/ash';
$configPrefix = 'ASH';
$configCandidates = [
    getenv($configPrefix . '_CONFIG') ?: getenv('HOME') . '/.ash/ash.yml',
    __DIR__ . '/ash.yml',
    $cwd . '/ash.yml',
];

foreach ($configCandidates as $candidate) {
    if (file_exists($candidate)) {
        $configFilePath = $candidate;
        break;
    }
}

// Define our Runner, and pass it the command classes we provide.
$runner = new \Robo\Runner($commandClasses);
$runner
    ->setSelfUpdateRepository($selfUpdateRepository)
    ->setConfigurationFilename($configFilePath)
    ->setEnvConfigPrefix($configPrefix)
    ->setClassLoader($loader);

// Execute the command and return the result.
$output = new \Symfony\Component\Console\Output\ConsoleOutput();

// Detect running as "drush".
// A symlink from 'drush' to 'ash' allows traditional global drush behavior.
// $argv[0] is whatever full string was used to call it.
// Could be a full path. Could be ./drush
// So just check that the end of the command is "drush"
if (str_ends_with($argv[0], 'drush')) {
  if (!empty($argv[1]) && strpos($argv[1], '@') === 0) {
    // "drush @alias x"

    $alias = $argv[1];
    $argv_new = [
      __DIR__ . '/ash',
      'site:exec',
      $alias,
      # Assume PATH has been set to composer bin.
      'drush'
    ];
    $argv_slice = array_slice($argv, 2);
    $argv = array_merge($argv_new, $argv_slice);
  }
  else {
    // "drush x"
    $argv_new = [
      __DIR__ . '/ash',
      'site:exec',
      # Assume PATH has been set to composer bin.
      'drush'
    ];
    $argv_slice = array_slice($argv, 1);
    $argv = array_merge($argv_new, $argv_slice);
  }
}
else {

  // Detect alias and push args.
  if (!empty($argv[1]) && strpos($argv[1], '@') === 0) {
    $argv_new = [
      $argv[0],
      'site:exec',
      $argv[1],
    ];
    $argv_slice = array_slice($argv, 2);
    $argv = array_merge($argv_new, $argv_slice);
  }
}

if (in_array('-v', $argv)) {
  $output->writeln("Welcome the ash CLI.");
  $output->writeln("====================");
  $output->writeln('Config File: ' . $configFilePath);
  $output->writeln('Command Directories: ');
  $output->writeln($directoryList);
  $output->writeln("====================");
}
$statusCode = $runner->execute($argv, $appName, $appVersion, $output);
exit($statusCode);

//// Set up environment
//$environment = new Environment(Path::getHomeDirectory(), $cwd, $autoloadFile);
//$environment->setConfigFileVariant(Drush::getMajorVersion());
//$environment->setLoader($loader);
//$environment->applyEnvironment();
//
//// Preflight and run
//$preflight = new Preflight($environment);
//$di = new DependencyInjection();
//$di->desiredHandlers(['errorHandler', 'shutdownHandler']);
//$runtime = new Runtime($preflight, $di);
//$status_code = $runtime->run($_SERVER['argv']);
//
//exit($status_code);
