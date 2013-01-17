<?php

final class PhabricatorPhabricatorOAuthConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Phabricator OAuth");
  }

  public function getDescription() {
    return pht("Configure Phabricator's OAuth provider.");
  }

  public function getOptions() {
    return array(
      $this->newOption('phabricator.oauth-uri', 'string', null)
        ->setDescription(
          pht(
            "The URI of the Phabricator instance to use as an OAuth server."))
        ->addExample('https://phabricator.example.com/', pht('Valid Setting')),
      $this->newOption('phabricator.auth-enabled', 'bool', false)
        ->setDescription(
          pht(
            "Can users use Phabricator credentials to login to Phabricator?")),
      $this->newOption('phabricator.registration-enabled', 'bool', true)
        ->setDescription(
          pht(
            "Can users use Phabricator credentials to create new Phabricator ".
            "accounts?")),
      $this->newOption('phabricator.auth-permanent', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Permanent"),
            pht("Able to be unlinked"),
          ))
        ->setDescription(
          pht(
            "Are Phabricator accounts permanently linked to Phabricator ".
            "accounts, or can the user unlink them?")),
      $this->newOption('phabricator.application-id', 'string', null)
        ->setDescription(
          pht(
            "The Phabricator 'Client ID' to use for Phabricator API access.")),
      $this->newOption('phabricator.application-secret', 'string', null)
        ->setMasked(true)
        ->setDescription(
          pht(
            "The Phabricator 'Client Secret' to use for Phabricator API ".
            "access.")),
     );
  }

}
