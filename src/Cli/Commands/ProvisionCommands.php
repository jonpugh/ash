<?php


namespace Ash\Cli\Commands;

use Ash\Cli\AshCommands;
use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasName;
use Consolidation\SiteAlias\SiteSpecParser;
use Consolidation\SiteProcess\ProcessManager;
use Robo\Exception\TaskException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Yaml\Yaml;

class ProvisionCommands extends AshCommands
{

    /**
     * Prepare a site codebase. Clone from git, checkout to the desired git reference, run build command.
     *
     * @command site:init
     * @aliases init
     * @format yaml
     * @return array
     *
     * @usage @site.local init
     */
    public function siteInit($alias_name, array $command_array)
    {
        $processManager = ProcessManager::createDefault();

        $site_alias = $this->manager->getAlias($alias_name);
        if (empty($site_alias)) {
            throw new \Exception("No aliases found");
        }

        // Remote aliases can't work because the root may not exist.
        if (!$site_alias->isLocal()) {
          throw new \Exception("Alias is not local. Cannot init.");
        }

        // If the site code does not exist...
        if (!file_exists($site_alias->root())) {
          if (!$site_alias->has('git_remote')) {
            throw new \Exception("Site alias has no 'git_remote' set. Cannot init.");
          }

          // Clone it.
          $this->io()->say('Site codebase not found');
          $this->io()->confirm(strtr('Clone <comment>:git_remote</comment> to <comment>:path</comment>?', [
            ':path' => $site_alias->root(),
            ':git_remote' => $site_alias->get('git_remote')
          ]));
          $task = $this->taskGitStack()
            ->stopOnFail()
            ->cloneRepo($site_alias->get('git_remote'), $site_alias->root(), $site_alias->get('git_reference'))
            ->run();
          if (!$task->wasSuccessful()) {
            throw new \Exception('Something went wrong when cloning the codebase.');
          }
        }

        if (file_exists($site_alias->root())) {
          $task = $this->taskGitStack()
            ->stopOnFail()
            ->dir($site_alias->root())
            ->checkout($site_alias->get('git_reference'))
            ->run();
          if (!$task->wasSuccessful()) {
            throw new \Exception('Something went wrong when cloning the codebase.');
          }
          else {
            $this->io()->success('Successfully checked out git reference '. $site_alias->get('git_reference'));
          }

          $this->io()->success(strtr('Site codebase found at :path', [':path' => $site_alias->root()]));
        }
        else {
          throw new \Exception('Site codebase not found.');
        }

        $task = $this->taskExecStack()
          ->exec('git show --compact-summary')
          ->exec('git status')
          ->run();

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

    /**
     * Add aliases to local config.
     * @return void
     * @aliases add
     */
    public function siteAdd() {
        // If drush/sites was found, offer to add it to inventory.
        $aliases_dir = getcwd() . '/drush/sites';
        if (file_exists($aliases_dir)) {
            $this->io()->info("Site aliases found in $aliases_dir.");
            $name = $this->io()->ask('Name?', strtr(basename(getcwd()), ['.' => '']));

            $alias_data = new SiteAlias();
            $alias_data->set('root', getcwd());

            // @TODO: What should the global alias be called? Should it be configurable?
            $alias_contents = Yaml::dump(['local' => $alias_data->export()]);

            $this->io->table(['Name', 'Contents'], [
                [$name, $alias_contents]
            ]);

            $alias_dirs = $this->config['alias_directories'];
            $choice = [];
            foreach ($alias_dirs as $dir) {
                $choice[] = "{$dir}/{$name}.site.yml";
            }

            $filename = $this->io()->choice('Write new alias file?', $choice, 0);

            if (file_exists($filename)) {
                $this->io()->warning("File exists at path $filename.");
                $this->io()->confirm('Overwrite?');
            }
            file_put_contents($filename, $alias_contents);

            $this->io()->success("Alias file written to $filename. Call 'ash @$name' to access the site");

        }
        else {
            throw new \Exception('No drush/sites folder found. Run "ash site:add" in the root of the site.');
        }
    }
}
