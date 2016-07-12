<?php

final class PhabricatorRecaptchaConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Integration with Recaptcha');
  }

  public function getDescription() {
    return pht('Configure Recaptcha captchas.');
  }

  public function getIcon() {
    return 'fa-recycle';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {

    return array(
      $this->newOption('recaptcha.enabled', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Enable Recaptcha'),
            pht('Disable Recaptcha'),
          ))
        ->setSummary(pht('Enable captchas with Recaptcha.'))
        ->setDescription(
          pht(
            'Enable recaptcha to require users solve captchas after a few '.
            'failed login attempts. This hinders brute-force attacks against '.
            'user passwords. For more information, see http://recaptcha.net/')),
      $this->newOption('recaptcha.public-key', 'string', null)
        ->setDescription(
          pht('Recaptcha public key, obtained by signing up for Recaptcha.')),
      $this->newOption('recaptcha.private-key', 'string', null)
        ->setHidden(true)
        ->setDescription(
          pht('Recaptcha private key, obtained by signing up for Recaptcha.')),
    );
  }

}
