<?php

declare(strict_types=1);

namespace Ash;

use Composer\InstalledVersions;

/**
 * Static Service Container wrapper.
 *
 * This code ~is~ will be analogous to the \Drupal or \Drush class.
 *
 * I didn't want to add the whole thing before we can use it.
 *
 * See https://github.com/drush-ops/drush/blob/12.x/src/Drush.php
 *
 */
class Ash
{
    /**
     * The version of Ash from the ash.info file, or FALSE if not read yet.
     *
     * @var string|FALSE
     */
    protected static $version = false;
    protected static $majorVersion = false;
    protected static $minorVersion = false;

    /**
     * The Robo Runner -- manages and constructs all commandfile classes
     *
     * @var Runner
     */
    protected static $runner;

    /**
     * Number of seconds before timeout for subprocesses. Can be customized via setTimeout() method.
     *
     * @var int
     */
    protected const TIMEOUT = 14400;

    public static function getTimeout(): int
    {
        return self::TIMEOUT;
    }

    /**
     * Return the current Ash version.
     *
     * n.b. Called before the DI container is initialized.
     * Do not log, etc. here.
     */
    public static function getVersion()
    {
        if (!self::$version) {
            self::$version = InstalledVersions::getVersion('ash/ash');
        }
        return self::$version;
    }

    /**
     * Convert internal Composer dev version to ".x"
     */
    public static function sanitizeVersionString($version)
    {
        return preg_replace('#\.9+\.9+\.9+#', '.x', $version);
    }

    public static function getMajorVersion(): string
    {
        if (!self::$majorVersion) {
            $ash_version = self::getVersion();
            $version_parts = explode('.', $ash_version);
            self::$majorVersion = $version_parts[0];
        }
        return self::$majorVersion;
    }

    public static function getMinorVersion(): string
    {
        if (!self::$minorVersion) {
            $ash_version = self::getVersion();
            $version_parts = explode('.', $ash_version);
            self::$minorVersion = $version_parts[1];
        }
        return self::$minorVersion;
    }

    /**
     * Sets a new global container.
     */
    public static function setContainer($container): void
    {
        Robo::setContainer($container);
    }

    /**
     * Unsets the global container.
     */
    public static function unsetContainer(): void
    {
        Robo::unsetContainer();
    }

    /**
     * Returns the currently active global container.
     *
     * @throws RuntimeException
     */
    public static function getContainer(): \Psr\Container\ContainerInterface
    {
        if (!Robo::hasContainer()) {
            throw new RuntimeException('Ash::$container is not initialized yet. \Ash::setContainer() must be called with a real container.');
        }
        return Robo::getContainer();
    }

    /**
     * Returns TRUE if the container has been initialized, FALSE otherwise.
     */
    public static function hasContainer(): bool
    {
        return Robo::hasContainer();
    }

    /**
     * Get the current Symfony Console Application.
     */
    public static function getApplication(): Application
    {
        return self::getContainer()->get('application');
    }

    /**
     * Return the Robo runner.
     */
    public static function runner(): Runner
    {
        if (!isset(self::$runner)) {
            self::$runner = new Runner();
        }
        return self::$runner;
    }

    /**
     * Retrieves a service from the container.
     *
     * Use this method if the desired service is not one of those with a dedicated
     * accessor method below. If it is listed below, those methods are preferred
     * as they can return useful type hints.
     *
     * @param string $id
     *   The ID of the service to retrieve.
     */
    public static function service(string $id)
    {
        return self::getContainer()->get($id);
    }

    /**
     * Indicates if a service is defined in the container.
     */
    public static function hasService(string $id): bool
    {
        // Check hasContainer() first in order to always return a Boolean.
        return self::hasContainer() && self::getContainer()->has($id);
    }

    /**
     * Return command factory
     */
    public static function commandFactory(): AnnotatedCommandFactory
    {
        return self::service('commandFactory');
    }

    /**
     * Return the Ash logger object.
     *
     * @internal Commands should use $this->logger() instead.
     */
    public static function logger(): LoggerInterface
    {
        return self::service('logger');
    }

    /**
     * Return the configuration object
     *
     * @internal Commands should use $this->config() instead.
     */
    public static function config(): AshConfig
    {
        return self::service('config');
    }

    /**
     * @internal Commands should use $this->siteAliasManager() instead.
     */
    public static function aliasManager(): SiteAliasManager
    {
        return self::service('site.alias.manager');
    }

    /**
     * @internal Commands should use $this->processManager() instead.
     */
    public static function processManager(): ProcessManager
    {
        return self::service('process.manager');
    }

    /**
     * Return the input object
     */
    public static function input(): InputInterface
    {
        return self::service('input');
    }

    /**
     * Return the output object
     */
    public static function output(): OutputInterface
    {
        return self::service('output');
    }

    /**
     * Run a Ash command on a site alias (or @self).
     *
     * Tip: Use injected processManager() instead of this method. See below.
     *
     * A class should use ProcessManagerAwareInterface / ProcessManagerAwareTrait
     * in order to have the Process Manager injected by Ash's DI container.
     * For example:
     * <code>
     *     use Consolidation\SiteProcess\ProcessManagerAwareTrait;
     *     use Consolidation\SiteProcess\ProcessManagerAwareInterface;
     *
     *     abstract class AshCommands implements ProcessManagerAwareInterface ...
     *     {
     *         use ProcessManagerAwareTrait;
     *     }
     * </code>
     * Since AshCommands already uses ProcessManagerAwareTrait, all Ash
     * commands may use the process manager to call other Ash commands.
     * Other classes will need to ensure that the process manager is injected
     * as shown above.
     *
     * Note, however, that an alias record is required to use the `ash` method.
     * The alias manager will provide an alias record, but the alias manager is
     * not injected by default into Ash commands. In order to use it, it is
     * necessary to use SiteAliasManagerAwareTrait:
     * <code>
     *     use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
     *     use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
     *
     *     class SiteInstallCommands extends AshCommands implements SiteAliasManagerAwareInterface
     *     {
     *         use SiteAliasManagerAwareTrait;
     *
     *         public function install(array $profile, ...)
     *         {
     *             $selfRecord = $this->siteAliasManager()->getSelf();
     *             $args = ['system.site', ...];
     *             $options = ['yes' => true];
     *             $process = $this->processManager()->ash($selfRecord, 'config-set', $args, $options);
     *             $process->mustRun();
     *         }
     *     }
     * </code>
     * Objects that are fetched from the DI container, or any Ash command will
     * automatically be given a reference to the alias manager if SiteAliasManagerAwareTrait
     * is used. Other objects will need to be manually provided with a reference
     * to the alias manager once it is created (call $obj->setAliasManager($aliasManager);).
     *
     * Clients that are using Ash::ash(), and need a reference to the alias
     * manager may use Ash::aliasManager().
     *
     */
    public static function ash(SiteAliasInterface $siteAlias, string $command, array $args = [], array $options = [], array $options_double_dash = []): SiteProcess
    {
        return self::processManager()->ash($siteAlias, $command, $args, $options, $options_double_dash);
    }

    /**
     * Run a bash fragment on a site alias.
     *
     * Use \Ash\Ash::ash() instead of this method when calling Ash.
     *
     * Tip: Commands can consider using $this->processManager() instead of this method.
     */
    public static function siteProcess(SiteAliasInterface $siteAlias, array $args = [], array $options = [], array $options_double_dash = []): ProcessBase
    {
        return self::processManager()->siteProcess($siteAlias, $args, $options, $options_double_dash);
    }

    /**
     * Run a bash fragment locally.
     *
     * The timeout parameter on this method doesn't work. It exists for compatibility with parent.
     * Call this method to get a Process and then call setters as needed.
     *
     * Tip: Consider using injected process manager instead of this method.
     *
     * @param string|array   $commandline The command line to run
     * @param string|null    $cwd         The working directory or null to use the working dir of the current PHP process
     * @param array|null     $env         The environment variables or null to use the same environment as the current PHP process
     * @param mixed|null     $input       The input as stream resource, scalar or \Traversable, or null for no input
     * @param int|float|null $timeout     The timeout in seconds or null to disable
     *
     * @return
     *   A wrapper around Symfony Process.
     */
    public static function process($commandline, $cwd = null, $env = null, $input = null, $timeout = 60): ProcessBase
    {
        return self::processManager()->process($commandline, $cwd, $env, $input, $timeout);
    }

    /**
     * Create a Process instance from a commandline string.
     *
     * Tip: Consider using injected process manager instead of this method.
     *
     * @param string $command The commandline string to run
     * @param string|null $cwd     The working directory or null to use the working dir of the current PHP process
     * @param array|null $env     The environment variables or null to use the same environment as the current PHP process
     * @param mixed|null $input   The input as stream resource, scalar or \Traversable, or null for no input
     * @param int|float|null $timeout The timeout in seconds or null to disable
     *
     * @return
     *   A wrapper around Symfony Process.
     */
    public static function shell(string $command, $cwd = null, array $env = null, $input = null, $timeout = 60): ProcessBase
    {
        return self::processManager()->shell($command, $cwd, $env, $input, $timeout);
    }

    /**
     * Return 'true' if we are in simulated mode
     *
     * @internal Commands should use $this->getConfig()->simulate().
     */
    public static function simulate()
    {
        return Ash::config()->simulate();
    }

    /**
     * Return 'true' if we are in affirmative mode
     */
    public static function affirmative()
    {
        if (!self::hasService('input')) {
            throw new \Exception('No input service available.');
        }
        return Ash::input()->getOption('yes');
    }

    /**
     * Return 'true' if we are in negative mode
     */
    public static function negative()
    {
        if (!self::hasService('input')) {
            throw new \Exception('No input service available.');
        }
        return Ash::input()->getOption('no');
    }

    /**
     * Return 'true' if we are in verbose mode
     */
    public static function verbose(): bool
    {
        if (!self::hasService('output')) {
            return false;
        }
        return Ash::output()->isVerbose();
    }

    /**
     * Return 'true' if we are in debug mode
     */
    public static function debug(): bool
    {
        if (!self::hasService('output')) {
            return false;
        }
        return Ash::output()->isDebug();
    }

    /**
     * Return the Bootstrap Manager.
     */
    public static function bootstrapManager(): BootstrapManager
    {
        return self::service('bootstrap.manager');
    }

    /**
     * Return the Bootstrap object.
     */
    public static function bootstrap(): Boot
    {
        return self::bootstrapManager()->bootstrap();
    }

    public static function redispatchOptions($input = null)
    {
        $input = $input ?: self::input();
        $command_name = $input->getFirstArgument();

        // $input->getOptions() returns an associative array of option => value
        $options = $input->getOptions();

        // The 'runtime.options' config contains a list of option names on th cli
        $optionNamesFromCommandline = self::config()->get('runtime.options');

        // Attempt to normalize option names.
        foreach ($optionNamesFromCommandline as $key => $name) {
            try {
                $optionNamesFromCommandline[$key] = Ash::getApplication()->get($command_name)->getDefinition()->shortcutToName($name);
            } catch (InvalidArgumentException $e) {
                // Do nothing. It's expected.
            }
        }

        // Remove anything in $options that was not on the cli
        $options = array_intersect_key($options, array_flip($optionNamesFromCommandline));

        // Don't suppress output as it is usually needed in redispatches. See https://github.com/ash-ops/ash/issues/4805 and https://github.com/ash-ops/ash/issues/4933
        unset($options['quiet']);

        // Add in the 'runtime.context' items, which includes --include, --alias-path et. al.
        return $options + array_filter(self::config()->get(PreflightArgs::ASH_RUNTIME_CONTEXT_NAMESPACE));
    }
}
