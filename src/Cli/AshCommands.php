<?php

namespace Ash\Cli;

use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasFileLoader;
use Consolidation\SiteAlias\SiteAliasManager;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteAlias\SiteAliasTrait;
use Consolidation\SiteAlias\Util\YamlDataFileLoader;
use Consolidation\SiteAlias\SiteSpecParser;
use Consolidation\SiteAlias\SiteAliasName;
use Consolidation\SiteProcess\ProcessManager;
use Symfony\Component\Translation\Dumper\YamlFileDumper;
use Symfony\Component\Yaml\Yaml;

class AshCommands extends \Robo\Tasks
{
    protected $aliasLoader;
    protected $aliases;
    protected $aliasName;
    protected $config;
    protected $manager;
    protected $processManager;

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
        $this->processManager = new ProcessManager();

    }
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

}