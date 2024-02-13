<?php


namespace Ash\Commands;

use Ash\AshCommands;
use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteSpecParser;
use Consolidation\SiteProcess\ProcessManager;
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
}
