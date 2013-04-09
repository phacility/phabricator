<?php

final class PhabricatorCoreConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Core");
  }

  public function getDescription() {
    return pht("Configure core options, including URIs.");
  }

  public function getOptions() {
    return array(
      $this->newOption('phabricator.base-uri', 'string', null)
        ->setLocked(true)
        ->setSummary(pht("URI where Phabricator is installed."))
        ->setDescription(
          pht(
            "Set the URI where Phabricator is installed. Setting this ".
            "improves security by preventing cookies from being set on other ".
            "domains, and allows daemons to send emails with links that have ".
            "the correct domain."))
        ->addExample('http://phabricator.example.com/', pht('Valid Setting')),
      $this->newOption('phabricator.production-uri', 'string', null)
        ->setSummary(
          pht("Primary install URI, for multi-environment installs."))
        ->setDescription(
          pht(
            "If you have multiple Phabricator environments (like a ".
            "development/staging environment for working on testing ".
            "Phabricator, and a production environment for deploying it), ".
            "set the production environment URI here so that emails and other ".
            "durable URIs will always generate with links pointing at the ".
            "production environment. If unset, defaults to ".
            "{{phabricator.base-uri}}. Most installs do not need to set ".
            "this option."))
        ->addExample('http://phabricator.example.com/', pht('Valid Setting')),
      $this->newOption('phabricator.timezone', 'string', null)
        ->setSummary(
          pht("The timezone Phabricator should use."))
        ->setDescription(
          pht(
            "PHP requires that you set a timezone in your php.ini before ".
            "using date functions, or it will emit a warning. If this isn't ".
            "possible (for instance, because you are using HPHP) you can set ".
            "some valid constant for date_default_timezone_set() here and ".
            "Phabricator will set it on your behalf, silencing the warning."))
        ->addExample('America/New_York', pht('US East (EDT)'))
        ->addExample('America/Chicago', pht('US Central (CDT)'))
        ->addExample('America/Boise', pht('US Mountain (MDT)'))
        ->addExample('America/Los_Angeles', pht('US West (PDT)')),
      $this->newOption('phabricator.show-beta-applications', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Install Beta Applications'),
            pht('Uninstall Beta Applications')
          ))
        ->setDescription(
          pht(
            "Phabricator includes 'Beta' applications which are in an early ".
            "stage of development. They range from very rough prototypes to ".
            "relatively complete (but unpolished) applications.\n\n".
            "By default, Beta applications are not installed. You can enable ".
            "this option to install them if you're interested in previewing ".
            "upcoming features.\n\n".
            "After enabling Beta applications, you can selectively uninstall ".
            "them (like normal applications).")),
      $this->newOption('phabricator.serious-business', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Serious business'),
            pht('Shenanigans'), // That should be interesting to translate. :P
          ))
        ->setSummary(
          pht("Should Phabricator be serious?"))
        ->setDescription(
          pht(
            "By default, Phabricator includes some silly nonsense in the UI, ".
            "such as a submit button called 'Clowncopterize' in Differential ".
            "and a call to 'Leap Into Action'. If you'd prefer more ".
            "traditional UI strings like 'Submit', you can set this flag to ".
            "disable most of the jokes and easter eggs.")),
       $this->newOption('environment.append-paths', 'list<string>', array())
        ->setSummary(
          pht("These paths get appended to your \$PATH envrionment variable."))
        ->setDescription(
          pht(
            "Phabricator occasionally shells out to other binaries on the ".
            "server. An example of this is the \"pygmentize\" command, used ".
            "to syntax-highlight code written in languages other than PHP. ".
            "By default, it is assumed that these binaries are in the \$PATH ".
            "of the user running Phabricator (normally 'apache', 'httpd', or ".
            "'nobody'). Here you can add extra directories to the \$PATH ".
            "environment variable, for when these binaries are in ".
            "non-standard locations. Note that you can also put binaries in ".
            "`phabricator/support/bin`."))
        ->addExample('/usr/local/bin', pht('Add One Path'))
        ->addExample("/usr/bin\n/usr/local/bin", pht('Add Multiple Paths')),
       $this->newOption('tokenizer.ondemand', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Query on demand"),
            pht("Query on page load"),
          ))
        ->setSummary(
          pht("Query for tokenizer fields on demand."))
        ->setDescription(
          pht(
            "Tokenizers are UI controls which let the user select other ".
            "users, email addresses, project names, etc., by typing the ".
            "first few letters and having the control autocomplete from a ".
            "list. They can load their data in two ways: either in a big ".
            "chunk up front, or as the user types. By default, the data is ".
            "loaded in a big chunk. This is simpler and performs better for ".
            "small datasets. However, if you have a very large number of ".
            "users or projects, (in the ballpark of more than a thousand), ".
            "loading all that data may become slow enough that it's ".
            "worthwhile to query on demand instead. This makes the typeahead ".
            "slightly less responsive but overall performance will be much ".
            "better if you have a ton of stuff. You can figure out which ".
            "setting is best for your install by changing this setting and ".
            "then playing with a user tokenizer (like the user selectors in ".
            "Maniphest or Differential) and seeing which setting loads ".
            "faster and feels better.")),
      $this->newOption('config.lock', 'set', array())
        ->setLocked(true)
        ->setDescription(pht('Additional configuration options to lock.')),
      $this->newOption('config.hide', 'set', array())
        ->setLocked(true)
        ->setDescription(pht('Additional configuration options to hide.')),
      $this->newOption('config.mask', 'set', array())
        ->setLocked(true)
        ->setDescription(pht('Additional configuration options to mask.')),
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
        ->setDescription(
          pht('Array containing list of Uninstalled applications.')),
      $this->newOption('welcome.html', 'string', null)
        ->setLocked(true)
        ->setDescription(
          pht('Custom HTML to show on the main Phabricator dashboard.')),
      $this->newOption('phabricator.cache-namespace', 'string', null)
        ->setLocked(true)
        ->setDescription(pht('Cache namespace.')),
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
            "'http://' or 'https://'.",
            $key));
      }

      $domain = $uri->getDomain();
      if (strpos($domain, '.') === false) {
        throw new PhabricatorConfigValidationException(
          pht(
            "Config option '%s' is invalid. The URI must contain a dot ('.'), ".
            "like 'http://example.com/', not just a bare name like ".
            "'http://example/'. Some web browsers will not set cookies on ".
            "domains with no TLD.",
            $key));
      }

      $path = $uri->getPath();
      if ($path !== '' && $path !== '/') {
        throw new PhabricatorConfigValidationException(
          pht(
            "Config option '%s' is invalid. The URI must NOT have a path, ".
            "e.g. 'http://phabricator.example.com/' is OK, but ".
            "'http://example.com/phabricator/' is not. Phabricator must be ".
            "installed on an entire domain; it can not be installed on a ".
            "path.",
            $key));
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
            "be a valid timezone identifier recognized by PHP, like ".
            "'America/Los_Angeles'. You can find a list of valid identifiers ".
            "here: %s",
            $key,
            'http://php.net/manual/timezones.php'));
      }
    }



  }


}
