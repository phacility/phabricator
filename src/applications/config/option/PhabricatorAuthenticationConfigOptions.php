<?php

final class PhabricatorAuthenticationConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Authentication");
  }

  public function getDescription() {
    return pht("Options relating to authentication.");
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'auth.password-auth-enabled', 'bool', true)
        ->setBoolOptions(
          array(
            pht("Allow password authentication"),
            pht("Don't allow password authentication")
          ))
        ->setSummary(pht("Enables password-based authentication."))
        ->setDescription(
          pht(
            "Can users login with a username/password, or by following the ".
            "link from a password reset email? You can disable this and ".
            "configure one or more OAuth providers instead.")),
      $this->newOption('auth.sessions.web', 'int', 5)
        ->setSummary(
          pht("Number of web sessions a user can have simultaneously."))
        ->setDescription(
          pht(
            "Maximum number of simultaneous web sessions each user is ".
            "permitted to have. Setting this to '1' will prevent a user from ".
            "logging in on more than one browser at the same time.")),
     $this->newOption('auth.sessions.conduit', 'int', 5)
        ->setSummary(
          pht(
            "Number of simultaneous Conduit sessions each user is permitted."))
        ->setDescription(
          pht(
            "Maximum number of simultaneous Conduit sessions each user is ".
            "permitted to have.")),
     $this->newOption('auth.sshkeys.enabled', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Enable SSH key storage"),
            pht("Disable SSH key storage")))
        ->setSummary(
          pht("Allow users to associate SSH keys with their accounts."))
        ->setDescription(
          pht(
            "Set this true to enable the Settings -> SSH Public Keys panel, ".
            "which will allow users to associated SSH public keys with their ".
            "accounts. This is only really useful if you're setting up ".
            "services over SSH and want to use Phabricator for ".
            "authentication; in most situations you can leave this ".
            "disabled.")),
     $this->newOption('auth.require-email-verification', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Require email verification"),
            pht("Don't require email verification")
          ))
        ->setSummary(
          pht("Require email verification before a user can log in."))
        ->setDescription(
          pht(
            "If true, email addresses must be verified (by clicking a link ".
            "in an email) before a user can login. By default, verification ".
            "is optional unless 'auth.email-domains' is nonempty.")),
     $this->newOption('auth.email-domains', 'list<string>', array())
        ->setSummary(pht("Only allow registration from particular domains."))
        ->setDescription(
          pht(
            "You can restrict allowed email addresses to certain domains ".
            "(like 'yourcompany.com') by setting a list of allowed domains ".
            "here. Users will only be allowed to register using email ".
            "addresses at one of the domains, and will only be able to add ".
            "new email addresses for these domains. If you configure this, ".
            "it implies 'auth.require-email-verification'.\n\n".
            "You should omit the '@' from domains. Note that the domain must ".
            "match exactly. If you allow 'yourcompany.com', that permits ".
            "'joe@yourcompany.com' but rejects 'joe@mail.yourcompany.com'."))
        ->addExample(
          "yourcompany.com\nmail.yourcompany.com",
          pht('Valid Setting')),
     $this->newOption('auth.login-message', 'string', null)
        ->setLocked(true)
        ->setSummary(pht("A block of HTML displayed on the login screen."))
        ->setDescription(
          pht(
            "You can provide an arbitrary block of HTML here, which will ".
            "appear on the login screen. Normally, you'd use this to provide ".
            "login or registration instructions to users.")),
     $this->newOption('account.editable', 'bool', true)
        ->setBoolOptions(
          array(
            pht("Allow editing"),
            pht("Prevent editing")
          ))
        ->setSummary(
          pht(
            "Determines whether or not basic account information is ".
            "editable."))
        ->setDescription(
          pht(
            "Is basic account information (email, real name, profile ".
            "picture) editable? If you set up Phabricator to automatically ".
            "synchronize account information from some other authoritative ".
            "system, you can disable this to ensure information remains ".
            "consistent across both systems.")),
     $this->newOption('account.minimum-password-length', 'int', 8)
        ->setSummary(pht("Minimum password length."))
        ->setDescription(
          pht(
            "When users set or reset a password, it must have at least this ".
            "many characters.")),
    );
  }

}
