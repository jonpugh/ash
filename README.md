# Ash: Site Alias Shell

A global CLI to list sites and run commands on them. Very similar to global `drush site:alias` command. 

## Overview

This project is a global cli designed to run commands against multiple sites using Consolidation's [Site Aliases](https://github.com/consolidation/site-alias).

This is a possible replacement for global Drush.

## Commands

```
site
site:exec        [e] Run a command against a site (in the root directory.)
site:get         [get] Show contents of a single site alias.
site:list        [sl|ls] List available site aliases.
site:value       Access a value from a single alias.
site-spec
site-spec:parse  Parse a site specification.
```

## History

This tool was built from the ashes of a small tool created by the `consolidation/site-alias` team called [alias-tool](https://github.com/consolidation/site-alias/blob/3.0.1/alias-tool).

It was inspired by the need to retire global drush.

## Installation

This is a global CLI. It will have a phar file in the future but for now, you can install one of 2 ways:

1. Global Require.

    ```
    composer global require jonpugh/ash
    ```
   Then add COMPOSER_HOME to your path.

    ```
    export PATH="$HOME/.config/composer:$PATH"
    ```

2. Source install.

    ```
    git clone git@github.com:jonpugh/ash.git
    sudo ln -s $PWD/ash /usr/local/bin/ash
    ```

## Configuration

See [ash.yml](ash.yml) for default config:

```yaml
# Copy this file to ~/.ash/ash.yml if overriding is needed.
ash:
  alias_directories:
    - "${env.HOME}/.ash"
```

Put alias files in one of the directories defined in `alias_directories`.

Remember to name the files `$APP.site.yml` instead of `self.site.yml`

See Drush [Site Alias Documentation](https://www.drush.org/12.x/site-aliases/) for details on how to create alias files.


## Usage

Given you have added the example file [examples/operations.site.yml](./examples/operations.site.yml) to `$HOME/.ash/operations.site.yml`, you can run the following commands:

### List all aliases

```shell
# List all aliases from global config (~/.ash/*)
$ ash ls
'@ash.operations.local':
  root: /home/jonpugh/Work/Operations/operations/web
  uri: 'https://operations.lndo.site'
  env:
    HOME: /home/jonpugh
'@ash.operations.mars':
  root: /home/jonpugh/Work/Operations/operations/web
  uri: 'https://mars.lndo.site'
  env:
    HOME: /home/jonpugh
```

### List site local aliases.

If you are running `ash` from a drupal codebase, it will detect and load all aliases in the `drush/sites` folder.


```shell
# List all aliases from a specific site.
$ cd path/to/myproject
$ ash ls
'@self.prod':
  host: sites.watch
  user: platform
  root: /var/platform/projects/siteswatch/prod
  uri: 'https://sites.watch'
$ ash exec @self.prod drush status
```

These aliases are also compatible with drush.

### Run a command on the site

The main `ash site:exec` command uses `SiteAlias` & `SiteProcess`, so commands will automatically be run on the remote server via SSH. See additional options at https://www.drush.org/12.x/site-aliases/#additional-site-alias-options

Docker-compose aliases can also be used. https://www.drush.org/12.x/site-aliases/#docker-compose-and-other-transports) 

```shell
# Execute a command in the site's folder, on the site's server.
$ ash exec @self.prod drush status
Drupal version : 9.5.10                                                     
Site URI       : http://default                                              
PHP binary     : /usr/bin/php8.1                                             
PHP config     : /etc/php/8.1/cli/php.ini                                    
PHP OS         : Linux                                                       
PHP version    : 8.1.20                                               
```
If options are needed, use the `--` as divider between `ash` command and target command.
```shell

$ ash exec @ash.operations.live -- drush wd-tail --extended
```
Ash aliases are drush aliases, so this is an equivalent command:
```shell 
$ drush @ash.operations.live wd-tail --extended

```

## @TODO

- Allow aliases to only contain name.environment. Currently all aliases get `@ash` pre-pended.
- Allow syntax `ash @alias command` instead of `ash site:exec @alias command`. Need to find out how drush processes argv to set current alias.

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on the process for submitting pull requests to us.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [releases](https://github.com/consolidation/site-alias/releases) page.

## Authors

* **Jon Pugh**
* **Greg Anderson** - Original `alias-tool` script and command files.

See also the list of [contributors](https://github.com/jonpugh/ash/contributors) who participated in this project. Thanks also to all of the [drush contributors](https://github.com/drush-ops/drush/contributors) who contributed directly or indirectly to site aliases.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details
