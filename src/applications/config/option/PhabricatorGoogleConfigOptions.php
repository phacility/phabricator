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
        ->setOptions(
          array(
            pht("Disable Google Authentication"),
            pht("Enable Google Authentication"),
          ))
        ->setDescription(
          pht(
            'Allow users to login to Phabricator using Google credentials.')),
      $this->newOption('google.registration-enabled', 'bool', true)
        ->setOptions(
          array(
            pht("Disable Google Registration"),
            pht("Enable Google Registration"),
          ))
        ->setDescription(
          pht(
            'Allow users to create new Phabricator accounts using Google '.
            'credentials.')),
      $this->newOption('google.auth-permanent', 'bool', false)
        ->setOptions(
          array(
            pht("Allow Google Account Unlinking"),
            pht("Permanently Bind Google Accounts"),
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
        ->setDescription(
          pht(
            'Google "Secret" to use for Google API access.')),
    );
  }

}
