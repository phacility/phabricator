<?php

final class PhabricatorCoreConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Core');
  }

  public function getDescription() {
    return pht('Configure core options, including URIs.');
  }

  public function getFontIcon() {
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

    return array(
      $this->newOption('phabricator.base-uri', 'string', null)
        ->setLocked(true)
        ->setSummary(pht('URI where Phabricator is installed.'))
        ->setDescription(
          pht(
            'Set the URI where Phabricator is installed. Setting this '.
            'improves security by preventing cookies from being set on other '.
            'domains, and allows daemons to send emails with links that have '.
            'the correct domain.'))
        ->addExample('http://phabricator.example.com/', pht('Valid Setting')),
      $this->newOption('phabricator.production-uri', 'string', null)
        ->setSummary(
          pht('Primary install URI, for multi-environment installs.'))
        ->setDescription(
          pht(
            'If you have multiple Phabricator environments (like a '.
            'development/staging environment for working on testing '.
            'Phabricator, and a production environment for deploying it), '.
            'set the production environment URI here so that emails and other '.
            'durable URIs will always generate with links pointing at the '.
            'production environment. If unset, defaults to `%s`. Most '.
            'installs do not need to set this option.',
            'phabricator.base-uri'))
        ->addExample('http://phabricator.example.com/', pht('Valid Setting')),
      $this->newOption('phabricator.allowed-uris', 'list<string>', array())
        ->setLocked(true)
        ->setSummary(pht('Alternative URIs that can access Phabricator.'))
        ->setDescription(
          pht(
            "These alternative URIs will be able to access 'normal' pages ".
            "on your Phabricator install. Other features such as OAuth ".
            "won't work. The major use case for this is moving installs ".
            "across domains."))
        ->addExample(
          "http://phabricator2.example.com/\n".
          "http://phabricator3.example.com/",
          pht('Valid Setting')),
      $this->newOption('phabricator.timezone', 'string', null)
        ->setSummary(
          pht('The timezone Phabricator should use.'))
        ->setDescription(
          pht(
            "PHP requires that you set a timezone in your php.ini before ".
            "using date functions, or it will emit a warning. If this isn't ".
            "possible (for instance, because you are using HPHP) you can set ".
            "some valid constant for %s here and Phabricator will set it on ".
            "your behalf, silencing the warning.",
            'date_default_timezone_set()'))
        ->addExample('America/New_York', pht('US East (EDT)'))
        ->addExample('America/Chicago', pht('US Central (CDT)'))
        ->addExample('America/Boise', pht('US Mountain (MDT)'))
        ->addExample('America/Los_Angeles', pht('US West (PDT)')),
      $this->newOption('phabricator.cookie-prefix', 'string', null)
        ->setLocked(true)
        ->setSummary(
          pht(
            'Set a string Phabricator should use to prefix cookie names.'))
        ->setDescription(
          pht(
            'Cookies set for x.com are also sent for y.x.com. Assuming '.
            'Phabricator instances are running on both domains, this will '.
            'create a collision preventing you from logging in.'))
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
            "Phabricator includes prototype applications which are in an ".
            "**early stage of development**. By default, prototype ".
            "applications are not installed, because they are often not yet ".
            "developed enough to be generally usable. You can enable ".
            "this option to install them if you're developing Phabricator ".
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
            'By default, Phabricator includes some flavor text in the UI, '.
            'like a prompt to "Weigh In" rather than "Add Comment" in '.
            'Maniphest. If you\'d prefer more traditional UI strings like '.
            '"Add Comment", you can set this flag to disable most of the '.
            'extra flavor.')),
      $this->newOption('remarkup.ignored-object-names', 'string', '/^(Q|V)\d$/')
        ->setSummary(
          pht('Text values that match this regex and are also object names '.
          'will not be linked.'))
        ->setDescription(
          pht(
            'By default, Phabricator links object names in Remarkup fields '.
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
            "Phabricator occasionally shells out to other binaries on the ".
            "server. An example of this is the `%s` command, used to ".
            "syntax-highlight code written in languages other than PHP. By ".
            "default, it is assumed that these binaries are in the %s of the ".
            "user running Phabricator (normally 'apache', 'httpd', or ".
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
            'phabricator/support/bin/',
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
          pht('Customized settings for Phabricator applications.')),
      $this->newOption('welcome.html', 'string', null)
        ->setLocked(true)
        ->setDescription(
          pht('Custom HTML to show on the main Phabricator dashboard.')),
      $this->newOption('phabricator.cache-namespace', 'string', 'phabricator')
        ->setLocked(true)
        ->setDescription(pht('Cache namespace.')),
      $this->newOption('phabricator.allow-email-users', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Allow'),
            pht('Disallow'),
          ))
        ->setDescription(
           pht('Allow non-members to interact with tasks over email.')),
      $this->newOption('phabricator.silent', 'bool', false)
        ->setLocked(true)
        ->setBoolOptions(
          array(
            pht('Run Silently'),
            pht('Run Normally'),
          ))
        ->setSummary(pht('Stop Phabricator from sending any email, etc.'))
        ->setDescription(
          pht(
            'This option allows you to stop Phabricator from sending '.
            'any data to external services. Among other things, it will '.
            'disable email, SMS, repository mirroring, and HTTP hooks.'.
            "\n\n".
            'This option is intended to allow a Phabricator instance to '.
            'be exported, copied, imported, and run in a test environment '.
            'without impacting users. For example, if you are migrating '.
            'to new hardware, you could perform a test migration first, '.
            'make sure things work, and then do a production cutover '.
            'later with higher confidence and less disruption. Without '.
            'this flag, users would receive duplicate email during the '.
            'time the test instance and old production instance were '.
            'both in operation.')),
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
            "Config option '%s' is invalid. The URI must start with ".
            "%s' or '%s'.",
            'http://',
            'https://',
            $key));
      }

      $domain = $uri->getDomain();
      if (strpos($domain, '.') === false) {
        throw new PhabricatorConfigValidationException(
          pht(
            "Config option '%s' is invalid. The URI must contain a dot ".
            "('%s'), like '%s', not just a bare name like '%s'. Some web ".
            "browsers will not set cookies on domains with no TLD.",
            '.',
            'http://example.com/',
            'http://example/',
            $key));
      }

      $path = $uri->getPath();
      if ($path !== '' && $path !== '/') {
        throw new PhabricatorConfigValidationException(
          pht(
            "Config option '%s' is invalid. The URI must NOT have a path, ".
            "e.g. '%s' is OK, but '%s' is not. Phabricator must be installed ".
            "on an entire domain; it can not be installed on a path.",
            $key,
            'http://phabricator.example.com/',
            'http://example.com/phabricator/'));
      }
    }


    if ($key === 'phabricator.timezone') {
      $old = date_default_timezone_get();
      $ok = @date_default_timezone_set($value);
      @date_default_timezone_set($old);

      if (!$ok) {
        throw new PhabricatorConfigValidationException(
          pht(
            "Config option '%s' is invalid. The timezone identifier must ".
            "be a valid timezone identifier recognized by PHP, like '%s'. "."
            You can find a list of valid identifiers here: %s",
            $key,
            'America/Los_Angeles',
            'http://php.net/manual/timezones.php'));
      }
    }
  }


}
