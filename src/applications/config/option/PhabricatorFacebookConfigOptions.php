<?php

final class PhabricatorFacebookConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Integration with Facebook");
  }

  public function getDescription() {
    return pht("Facebook authentication and integration options.");
  }

  public function getOptions() {
    return array(
      $this->newOption('facebook.auth-enabled', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Enable Facebook Authentication"),
            pht("Disable Facebook Authentication"),
          ))
        ->setDescription(
          pht(
            'Allow users to login to Phabricator using Facebook credentials.')),
      $this->newOption('facebook.registration-enabled', 'bool', true)
        ->setBoolOptions(
          array(
            pht("Enable Facebook Registration"),
            pht("Disable Facebook Registration"),
          ))
        ->setDescription(
          pht(
            'Allow users to create new Phabricator accounts using Facebook '.
            'credentials.')),
      $this->newOption('facebook.auth-permanent', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Permanently Bind Facebook Accounts"),
            pht("Allow Facebook Account Unlinking"),
          ))
        ->setDescription(
          pht(
            'Are Phabricator accounts permanently bound to Facebook '.
            'accounts?')),
      $this->newOption('facebook.application-id', 'string', null)
        ->setDescription(
          pht(
            'Facebook "Application ID" to use for Facebook API access.')),
      $this->newOption('facebook.application-secret', 'string', null)
        ->setMasked(true)
        ->setDescription(
          pht(
            'Facebook "Application Secret" to use for Facebook API access.')),
      $this->newOption('facebook.require-https-auth', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Require HTTPS"),
            pht("Do Not Require HTTPS"),
          ))
        ->setSummary(
          pht(
            'Reject Facebook logins from accounts that do not have Facebook '.
            'configured in HTTPS-only mode.'))
        ->setDescription(
          pht(
            'You can require users logging in via Facebook auth have Facebook '.
            'set to HTTPS-only, which ensures their Facebook cookies are '.
            'SSL-only. This makes it more difficult for an attacker to '.
            'escalate a cookie-sniffing attack which captures Facebook '.
            'credentials into Phabricator access, but will require users '.
            'change their Facebook settings if they do not have this mode '.
            'enabled.')),
    );
  }

}
