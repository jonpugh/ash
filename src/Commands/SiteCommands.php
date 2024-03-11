<?php


namespace Ash\Commands;

use Ash\AshCommands;
use Consolidation\SiteAlias\SiteAlias;
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

        // Look up if this is a local command.
        if (isset($command_array[0]) && in_array($command_array[0], $this->config['commands']['site:exec']['local_commands'])) {

          // @TODO: Support all transports. isLocal() only looks for 'docker';
          if (!$site_alias->isLocal()) {
            // Remove "docker" site alias config.
            $data = $site_alias->export();
            unset($data['docker']);
            $site_alias->import($data);

            # Alias "root" is in the container. Set it to cwd instead?
            $site_alias->set('root', getcwd());
          }
        }
        // Set the drush URI to the site alias uri.
        $site_alias->set('env-vars', [
            'DRUSH_OPTIONS_URI' => $site_alias->uri() ?? '',
            'PATH' => getenv('PATH') . ':./vendor/bin:./bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/snap/bin',
        ]);

        $processManager = ProcessManager::createDefault();
        $process = $processManager->siteProcess($site_alias, $command_array);
        $process->setWorkingDirectory($site_alias->root());
        $process->setTimeout(null);
        $process->setTty($process->isTtySupported());

        // @TODO: Would it be kosher to try and detect bin-path?
        // That way users could `ash @alias drush` or any other bin.

        $process->mustRun(function ($type, $buffer): void {
            echo $buffer;
        });
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
          $location = $alias->remoteHostWithUser()? $alias->remoteHostWithUser() . ':' . $alias->root(): $alias->root();

          // @TODO: Is there a cleaner way to get the alias without the prefix?
          $name = str_replace('ash.', '', $alias->name());

          $row = [$name, $alias->uri(), $location];
          if ($this->output()->isVerbose()) {
//            $row[] = $alias->();
          }
          $rows[] = $row;
        }

        $this->io()->table(['Name', 'URI', 'Location'], $rows);
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
     * @option name Alias name.
     * @option root Alias root.
     * @return void
     * @aliases add
     */
    public function siteAdd($options = [
      'name' => '',
      'root' => '',
    ]) {
        $suggested_root = $options['root'] ?: getcwd();
        $suggested_name = $options['name'] ?: strtr(basename($suggested_root), ['.' => '']);
        $root = $this->io()->ask('Root?', $suggested_root);
        $name = $this->io()->ask('Name?', $suggested_name);

        $alias_data = new SiteAlias();
        $alias_data->set('root', $root);

        // @TODO: What should the global alias be called? Should it be configurable?
        $alias_contents = Yaml::dump(['default' => $alias_data->export()]);

        $this->io->table(['Name', 'Contents'], [
            [$name, $alias_contents]
        ]);

        $alias_dirs = $this->config['alias_directories'];
        $choice = [];
        foreach ($alias_dirs as $dir) {
            if (!file_exists($dir)) {
              if ($this->io()->confirm("Alias directory <comment>$dir</comment> does not exist. Create it?")) {
                mkdir($dir);
              }

            }
            $choice[] = "{$dir}/{$name}.site.yml";
        }

        $filename = $this->io()->choice('Alias file location?', $choice, 0);


        $file_existed = file_exists($filename);
        if (!$file_existed || $file_existed && $this->io()->confirm("File exists at path <comment>$filename</comment>. Overwrite?")) {
          file_put_contents($filename, $alias_contents);
          if ($file_existed) {
            $this->io()->warning("File $filename was overwritten.");
          }
        }
        else {
          if (file_exists($filename)) {
            $this->io()->error("File $filename already exists. Not overwritting.");
            exit(1);
          }
        }

        $this->io()->success("Alias file written to $filename. Call 'ash @$name' to access the site");
    }
}
