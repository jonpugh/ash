<?php

declare(strict_types=1);

namespace Ash\Commands;

use Ash\AshCommands;
use Consolidation\SiteProcess\Util\Shell;
use Consolidation\SiteProcess\Util\Tty;
use Symfony\Component\Console\Input\InputOption;

final class SshCommands extends AshCommands
{

    const SSH = 'site:ssh';
    const REQ = InputOption::VALUE_REQUIRED;

    /**
     * Connect to a webserver via SSH, and optionally run a shell command.
     *
     * @command site:ssh
     * @aliases ssh sh
     *
     */
    public function ssh($alias_name, array $code, $options = ['cd' => 0]): void
    {
        $alias = $this->manager->get($alias_name);

        $this->io()->note("Connecting to $alias_name via SSH...");

        if (empty($code)) {
            $code[] = 'bash';
            $code[] = '-l';

            // We're calling an interactive 'bash' shell, so we want to
            // force tty to true.
            $options['tty'] = true;
        }

        if ((count($code) == 1)) {
            $code = [Shell::preEscaped($code[0])];
        }
        print_r($code);

        $process = $this->processManager->siteProcess($alias, $code);
        if (Tty::isTtySupported()) {
            $process->setTty($options['tty']);
        }
        // The transport handles the chdir during processArgs().
        $fallback = $alias->hasRoot() ? $alias->root() : null;
        $process->setWorkingDirectory($options['cd'] ?: $fallback);
        $process->setRealtimeOutput($this->output);
        $process->mustRun($process->showRealtime());

    }
}
