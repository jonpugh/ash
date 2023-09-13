<?php

namespace JonPugh\Ash\Cli;

use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasFileLoader;
use Consolidation\SiteAlias\SiteAliasManager;
use Consolidation\SiteAlias\Util\YamlDataFileLoader;
use Consolidation\SiteAlias\SiteSpecParser;
use Consolidation\SiteAlias\SiteAliasName;
use Consolidation\SiteProcess\ProcessManager;

class AshCommands extends \Robo\Tasks
{
    protected $aliasLoader;

    protected $aliases;

    protected $aliasName;

    protected $config;

    public function __construct() {
        $this->config = \Robo\Robo::Config()->get('ash');

        $this->aliasLoader = new SiteAliasFileLoader();
        $ymlLoader = new YamlDataFileLoader();
        $this->aliasLoader->addLoader('yml', $ymlLoader);

        // Load local site aliases.
        $cwd = isset($_SERVER['PWD']) && is_dir($_SERVER['PWD']) ? $_SERVER['PWD'] : getcwd();
        $this->aliasLoader->addSearchLocation($cwd .'/drush/sites');

        // Parse environment vars
        $aliasName = $this->getLocationsAndAliasName($this->config['alias_directories']);
        $this->manager = new SiteAliasManager($this->aliasLoader);
        $this->aliases = $this->manager->getMultiple($aliasName);

    }

    /**
     * Run a command against a site (in the root directory and on the right server.)
     * You can use the alternative syntax: ash @alias command.
     *
     * @command site:exec
     * @format yaml
     * @return array
     *
     * @usage @site.local git status
     * @usage @site.local vendor/bin/drush user:login
     * @usage @site.local vendor/bin/drush @prod cr     # Calls `drush @prod cr` in the site root (where site local site aliases would be available.)
     * @usage -- ls -la  # To pass a command with dashes, use -- to separate ash command from site command.
     */
    public function siteExec($alias_name, array $command_array)
    {
        $site_alias = $this->manager->getAlias($alias_name);
        if (empty($site_alias)) {
            throw new \Exception("No aliases found");
        }

        // Set the drush URI to the site alias uri.
        $site_alias->set('env-vars', [
           'DRUSH_OPTIONS_URI' => $site_alias->uri(),
        ]);

        $processManager = ProcessManager::createDefault();
        $process = $processManager->siteProcess($site_alias, $command_array);
        $process->setWorkingDirectory($site_alias->root());

        // @TODO: Would it be kosher to try and detect bin-path?
        // That way users could `ash @alias drush` or any other bin.

        $process->mustRun(function ($type, $buffer): void {
            echo $buffer;
        });
        return $process->getExitCode();
    }

    /**
     * List available site aliases.
     *
     * @command site:list
     * @format yaml
     * @return array
     * @aliases sl ls
     */
    public function siteList()
    {
        return $this->renderAliases($this->aliases);
    }
//
//    /**
//     * Load available site aliases.
//     *
//     * @command site:load
//     * @format yaml
//     * @return array
//     */
//    public function siteLoad(array $dirs)
//    {
//        $this->aliasLoader = new SiteAliasFileLoader();
//        $ymlLoader = new YamlDataFileLoader();
//        $this->aliasLoader->addLoader('yml', $ymlLoader);
//
//        foreach ($dirs as $dir) {
//            $this->io()->note("Add search location: $dir");
//            $this->aliasLoader->addSearchLocation($dir);
//        }
//
//        $all = $this->aliasLoader->loadAll();
//
//        return $this->renderAliases($all);
//    }

    protected function getLocationsAndAliasName($varArgs)
    {
        $aliasName = '';
        foreach ($varArgs as $arg) {
            if (SiteAliasName::isAliasName($arg)) {
                $this->io()->note("Alias parameter: '$arg'");
                $aliasName = $arg;
            } else {
                $dir = $arg;
                $this->io()->note("Add search location: $dir");
                $this->aliasLoader->addSearchLocation($dir);
            }
        }
        return $aliasName;
    }

    /**
     * @param SiteAlias[] $all An array of aliases to display.
     */
    protected function renderAliases(array $all)
    {
        if (empty($all)) {
            throw new \Exception("No aliases found");
        }

        $rows = [];
        foreach ($all as $name => $alias) {
            $rows[] = [$alias->name(), $alias->root(), $alias->remoteHostWithUser()];
        }

        $this->io()->table(['Name', 'Root', 'Host'], $rows);
    }

    /**
     * Show contents of a single site alias.
     *
     * @command site:get
     * @aliases get
     * @format yaml
     * @return array
     */
    public function siteGet($aliasName)
    {
        $result = $this->manager->get($aliasName);
        if (!$result) {
            throw new \Exception("No alias found");
        }
        return $result->export();
    }

    /**
     * Access a value from a single alias.
     *
     * @command site:value
     * @format yaml
     * @return string
     */
    public function siteValue($aliasName, $key)
    {
        $result = $this->manager->get($aliasName);
        if (!$result) {
            throw new \Exception("No alias found");
        }
        if (!$result->get($key)) {
            throw new \Exception("Key $key was not found in alias $aliasName.");
        }
        return $result->get($key);
    }

    /**
     * Parse a site specification.
     *
     * @command site-spec:parse
     * @format yaml
     * @return array
     */
    public function parse($spec, $options = ['root' => ''])
    {
        $parser = new SiteSpecParser();
        return $parser->parse($spec, $options['root']);
    }
}