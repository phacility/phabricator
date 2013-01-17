<?php

final class PhabricatorGoogleConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Integration with Google");
  }

  public function getDescription() {
    return pht("Google authentication and integration options.");
  }

  public function getOptions() {
    return array(
      $this->newOption('google.auth-enabled', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Enable Google Authentication"),
            pht("Disable Google Authentication"),
          ))
        ->setDescription(
          pht(
            'Allow users to login to Phabricator using Google credentials.')),
      $this->newOption('google.registration-enabled', 'bool', true)
        ->setBoolOptions(
          array(
            pht("Enable Google Registration"),
            pht("Disable Google Registration"),
          ))
        ->setDescription(
          pht(
            'Allow users to create new Phabricator accounts using Google '.
            'credentials.')),
      $this->newOption('google.auth-permanent', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Permanently Bind Google Accounts"),
            pht("Allow Google Account Unlinking"),
          ))
        ->setDescription(
          pht(
            'Are Phabricator accounts permanently bound to Google '.
            'accounts?')),
      $this->newOption('google.application-id', 'string', null)
        ->setDescription(
          pht(
            'Google "Client ID" to use for Google API access.')),
      $this->newOption('google.application-secret', 'string', null)
        ->setMasked(true)
        ->setDescription(
          pht(
            'Google "Secret" to use for Google API access.')),
    );
  }

}
