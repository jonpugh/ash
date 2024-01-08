<?php


namespace Ash\Cli\Commands;

use Ash\Cli\AshCommands;
use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasName;
use Consolidation\SiteAlias\SiteSpecParser;
use Consolidation\SiteProcess\ProcessManager;
use Symfony\Component\Yaml\Yaml;

class SiteCommands extends AshCommands
{

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
        if ($this->io()->isVerbose()) {
            $this->io()->table(['Alias', 'Command'], [
                [$alias_name, implode(' ', $command_array)]
            ]);
        }
        $site_alias = $this->manager->getAlias($alias_name);
        if (empty($site_alias)) {
            throw new \Exception("No aliases found");
        }

        // Set the drush URI to the site alias uri.
        $site_alias->set('env-vars', [
            'DRUSH_OPTIONS_URI' => $site_alias->uri(),
            'PATH' => './vendor/bin:./bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:',
        ]);

        $processManager = ProcessManager::createDefault();
        $process = $processManager->siteProcess($site_alias, $command_array);
        $process->setWorkingDirectory($site_alias->root());
        $process->setTimeout(null);

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
            $alias_contents = Yaml::dump(['default' => $alias_data->export()]);

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
