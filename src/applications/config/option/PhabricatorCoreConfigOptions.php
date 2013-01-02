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
        ->setSummary(pht("URI where Phabricator is installed."))
        ->setDescription(
          pht(
            "Set the URI where Phabricator is installed. Setting this ".
            "improves security by preventing cookies from being set on other ".
            "domains, and allows daemons to send emails with links that have ".
            "the correct domain."))
        ->addExample('http://phabricator.example.com/', 'Valid Setting'),
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
        ->addExample('http://phabricator.example.com/', 'Valid Setting'),
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
      $this->newOption('phabricator.serious-business', 'bool', false)
        ->setOptions(
          array(
            pht('Shenanigans'), // That should be interesting to translate. :P
            pht('Serious business'),
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
       $this->newOption('storage.default-namespace', 'string', 'phabricator')
        ->setSummary(
          pht("The namespace that Phabricator databases should use."))
        ->setDescription(
          pht(
            "Phabricator puts databases in a namespace, which defualts to ".
            "'phabricator' -- for instance, the Differential database is ".
            "named 'phabricator_differential' by default. You can change ".
            "this namespace if you want. Normally, you should not do this ".
            "unless you are developing Phabricator and using namespaces to ".
            "separate multiple sandbox datasets."))
        ->addExample('phabricator', 'Valid Setting'),
       $this->newOption('environment.append-paths', 'list<string>', null)
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
            "non-standard locations."))
        ->addExample('/usr/local/bin', 'Valid Setting'),
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
