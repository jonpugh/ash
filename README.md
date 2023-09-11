# Ash
## Site Alias Shell

A global CLI to list sites and run commands on them.

This is a possible replacement for global Drush.

## Overview

This project is a global cli designed to run commands against multiple sites using [Site Aliases](https://github.com/consolidation/site-alias).

## Installation

This is a global CLI. It will be installable via composer global-require and a phar file.

Once it's available on packagist, I'll update instructions here.

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

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on the process for submitting pull requests to us.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [releases](https://github.com/consolidation/site-alias/releases) page.

## Authors

* **Greg Anderson**
* **Moshe Weitzman**

See also the list of [contributors](https://github.com/consolidation/site-alias/contributors) who participated in this project. Thanks also to all of the [drush contributors](https://github.com/drush-ops/drush/contributors) who contributed directly or indirectly to site aliases.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details