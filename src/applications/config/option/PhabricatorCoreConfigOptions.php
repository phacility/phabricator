<?php

final class PhabricatorCoreConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Core');
  }

  public function getDescription() {
    return pht('Configure core options, including URIs.');
  }

  public function getIcon() {
    return 'fa-bullseye';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    if (phutil_is_windows()) {
      $paths = array();
    } else {
      $paths = array(
        '/bin',
        '/usr/bin',
        '/usr/local/bin',
      );
    }

    $path = getenv('PATH');

    $proto_doc_href = PhabricatorEnv::getDoclink(
      'User Guide: Prototype Applications');
    $proto_doc_name = pht('User Guide: Prototype Applications');
    $applications_app_href = '/applications/';

    $silent_description = $this->deformat(pht(<<<EOREMARKUP
This option allows you to stop this service from sending data to most external
services: it will disable email, SMS, repository mirroring, remote builds,
Doorkeeper writes, and webhooks.

This option is intended to allow an instance to be exported, copied, imported,
and run in a test environment without impacting users. For example, if you are
migrating to new hardware, you could perform a test migration first with this
flag set, make sure things work, and then do a production cutover later with
higher confidence and less disruption.

Without making use of this flag to silence the temporary test environment,
users would receive duplicate email during the time the test instance and old
production instance were both in operation.
EOREMARKUP
      ));

    $timezone_description = $this->deformat(pht(<<<EOREMARKUP
PHP date functions will emit a warning if they are called when no default
server timezone is configured.

Usually, you configure a default timezone in `php.ini` by setting the
configuration value `date.timezone`.

If you prefer, you can configure a default timezone here instead. To configure
a default timezone, select a timezone from the
[[ %s | PHP List of Supported Timezones ]].
EOREMARKUP
,
      'https://php.net/manual/timezones.php'));


    return array(
      $this->newOption('phabricator.base-uri', 'string', null)
        ->setLocked(true)
        ->setSummary(pht('URI where this software is installed.'))
        ->setDescription(
          pht(
            'Set the URI where this software is installed. Setting this '.
            'improves security by preventing cookies from being set on other '.
            'domains, and allows daemons to send emails with links that have '.
            'the correct domain.'))
        ->addExample('http://devtools.example.com/', pht('Valid Setting')),
      $this->newOption('phabricator.production-uri', 'string', null)
        ->setSummary(
          pht('Primary install URI, for multi-environment installs.'))
        ->setDescription(
          pht(
            'If you have multiple %s environments (like a '.
            'development/staging environment and a production environment), '.
            'set the production environment URI here so that emails and other '.
            'durable URIs will always generate with links pointing at the '.
            'production environment. If unset, defaults to `%s`. Most '.
            'installs do not need to set this option.',
            PlatformSymbols::getPlatformServerName(),
            'phabricator.base-uri'))
        ->addExample('http://devtools.example.com/', pht('Valid Setting')),
      $this->newOption('phabricator.allowed-uris', 'list<string>', array())
        ->setLocked(true)
        ->setSummary(pht('Alternative URIs that can access this service.'))
        ->setDescription(
          pht(
            "These alternative URIs will be able to access 'normal' pages ".
            "on your this install. Other features such as OAuth ".
            "won't work. The major use case for this is moving installs ".
            "across domains."))
        ->addExample(
          "http://phabricator2.example.com/\n".
          "http://phabricator3.example.com/",
          pht('Valid Setting')),
      $this->newOption('phabricator.timezone', 'string', null)
        ->setSummary(
          pht('The timezone this software should use by default.'))
        ->setDescription($timezone_description)
        ->addExample('America/New_York', pht('US East (EDT)'))
        ->addExample('America/Chicago', pht('US Central (CDT)'))
        ->addExample('America/Boise', pht('US Mountain (MDT)'))
        ->addExample('America/Los_Angeles', pht('US West (PDT)')),
      $this->newOption('phabricator.cookie-prefix', 'string', null)
        ->setLocked(true)
        ->setSummary(
          pht(
            'Set a string this software should use to prefix cookie names.'))
        ->setDescription(
          pht(
            'Cookies set for x.com are also sent for y.x.com. Assuming '.
            'instances are running on both domains, this will create a '.
            'collision preventing you from logging in.'))
        ->addExample('dev', pht('Prefix cookie with "%s"', 'dev')),
      $this->newOption('phabricator.show-prototypes', 'bool', false)
        ->setLocked(true)
        ->setBoolOptions(
          array(
            pht('Enable Prototypes'),
            pht('Disable Prototypes'),
          ))
        ->setSummary(
          pht(
            'Install applications which are still under development.'))
        ->setDescription(
          pht(
            "IMPORTANT: The upstream does not provide support for prototype ".
            "applications.".
            "\n\n".
            "This platform includes prototype applications which are in an ".
            "**early stage of development**. By default, prototype ".
            "applications are not installed, because they are often not yet ".
            "developed enough to be generally usable. You can enable ".
            "this option to install them if you're developing applications ".
            "or are interested in previewing upcoming features.".
            "\n\n".
            "To learn more about prototypes, see [[ %s | %s ]].".
            "\n\n".
            "After enabling prototypes, you can selectively uninstall them ".
            "(like normal applications).",
            $proto_doc_href,
            $proto_doc_name)),
      $this->newOption('phabricator.serious-business', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Serious business'),
            pht('Shenanigans'), // That should be interesting to translate. :P
          ))
        ->setSummary(
          pht('Allows you to remove levity and jokes from the UI.'))
        ->setDescription(
          pht(
            'By default, this software includes some flavor text in the UI, '.
            'like a prompt to "Weigh In" rather than "Add Comment" in '.
            'Maniphest. If you\'d prefer more traditional UI strings like '.
            '"Add Comment", you can set this flag to disable most of the '.
            'extra flavor.')),
      $this->newOption(
        'remarkup.ignored-object-names',
        'string',

        // Q1, Q2, etc., are common abbreviations for "Quarter".
        // V1, V2, etc., are common abbreviations for "Version".
        // P1, P2, etc., are common abbreviations for "Priority".

        // M1 is a computer chip manufactured by Apple.
        // M2 (commonly spelled "M.2") is an expansion slot on motherboards.
        // M4 is a carbine.
        // M8 is a phonetic spelling of "mate", used in culturally significant
        // copypasta about navy seals.

        '/^(Q|V|M|P)\d$/')
        ->setSummary(
          pht('Text values that match this regex and are also object names '.
          'will not be linked.'))
        ->setDescription(
          pht(
            'By default, this software links object names in Remarkup fields '.
            'to the corresponding object. This regex can be used to modify '.
            'this behavior; object names that match this regex will not be '.
            'linked.')),
      $this->newOption('environment.append-paths', 'list<string>', $paths)
        ->setSummary(
          pht(
            'These paths get appended to your %s environment variable.',
            '$PATH'))
        ->setDescription(
          pht(
            "Thhi software sometimes executes other binaries on the ".
            "server. An example of this is the `%s` command, used to ".
            "syntax-highlight code written in languages other than PHP. By ".
            "default, it is assumed that these binaries are in the %s of the ".
            "user running this software (normally 'apache', 'httpd', or ".
            "'nobody'). Here you can add extra directories to the %s ".
            "environment variable, for when these binaries are in ".
            "non-standard locations.\n\n".
            "Note that you can also put binaries in `%s` (for example, by ".
            "symlinking them).\n\n".
            "The current value of PATH after configuration is applied is:\n\n".
            "  lang=text\n".
            "  %s",
            'pygmentize',
            '$PATH',
            '$PATH',
            'support/bin/',
            $path))
        ->setLocked(true)
        ->addExample('/usr/local/bin', pht('Add One Path'))
        ->addExample("/usr/bin\n/usr/local/bin", pht('Add Multiple Paths')),
      $this->newOption('config.lock', 'set', array())
        ->setLocked(true)
        ->setDescription(pht('Additional configuration options to lock.')),
      $this->newOption('config.hide', 'set', array())
        ->setLocked(true)
        ->setDescription(pht('Additional configuration options to hide.')),
      $this->newOption('config.ignore-issues', 'set', array())
        ->setLocked(true)
        ->setDescription(pht('Setup issues to ignore.')),
      $this->newOption('phabricator.env', 'string', null)
        ->setLocked(true)
        ->setDescription(pht('Internal.')),
      $this->newOption('test.value', 'wild', null)
        ->setLocked(true)
        ->setDescription(pht('Unit test value.')),
      $this->newOption('phabricator.uninstalled-applications', 'set', array())
        ->setLocked(true)
        ->setLockedMessage(pht(
          'Use the %s to manage installed applications.',
          phutil_tag(
            'a',
            array(
              'href' => $applications_app_href,
            ),
            pht('Applications application'))))
        ->setDescription(
          pht('Array containing list of uninstalled applications.')),
      $this->newOption('phabricator.application-settings', 'wild', array())
        ->setLocked(true)
        ->setDescription(
          pht('Customized settings for applications.')),
      $this->newOption('phabricator.cache-namespace', 'string', 'phabricator')
        ->setLocked(true)
        ->setDescription(pht('Cache namespace.')),
      $this->newOption('phabricator.silent', 'bool', false)
        ->setLocked(true)
        ->setBoolOptions(
          array(
            pht('Run Silently'),
            pht('Run Normally'),
          ))
        ->setSummary(pht('Stop this software from sending any email, etc.'))
        ->setDescription($silent_description),
      );

  }

  protected function didValidateOption(
    PhabricatorConfigOption $option,
    $value) {

    $key = $option->getKey();
    if ($key == 'phabricator.base-uri' ||
        $key == 'phabricator.production-uri') {

      $uri = new PhutilURI($value);
      $protocol = $uri->getProtocol();
      if ($protocol !== 'http' && $protocol !== 'https') {
        throw new PhabricatorConfigValidationException(
          pht(
            'Config option "%s" is invalid. The URI must start with '.
            '"%s" or "%s".',
            $key,
            'http://',
            'https://'));
      }

      $domain = $uri->getDomain();
      if (strpos($domain, '.') === false) {
        throw new PhabricatorConfigValidationException(
          pht(
            'Config option "%s" is invalid. The URI must contain a dot '.
            '("%s"), like "%s", not just a bare name like "%s". Some web '.
            'browsers will not set cookies on domains with no TLD.',
            $key,
            '.',
            'http://example.com/',
            'http://example/'));
      }

      $path = $uri->getPath();
      if ($path !== '' && $path !== '/') {
        throw new PhabricatorConfigValidationException(
          pht(
            "Config option '%s' is invalid. The URI must NOT have a path, ".
            "e.g. '%s' is OK, but '%s' is not. This software must be '.
            'installed on an entire domain; it can not be installed on a path.",
            $key,
            'http://devtools.example.com/',
            'http://example.com/devtools/'));
      }
    }


    if ($key === 'phabricator.timezone') {
      $old = date_default_timezone_get();
      $ok = @date_default_timezone_set($value);
      @date_default_timezone_set($old);

      if (!$ok) {
        throw new PhabricatorConfigValidationException(
          pht(
            'Config option "%s" is invalid. The timezone identifier must '.
            'be a valid timezone identifier recognized by PHP, like "%s".',
            $key,
            'America/Los_Angeles'));
      }
    }
  }


}
