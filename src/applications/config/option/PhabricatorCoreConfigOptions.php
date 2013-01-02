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
        ->addExample('http://phabricator.example.com/', 'Valid Setting')
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
  }


}
