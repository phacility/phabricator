<?php

final class PhabricatorPasteConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Paste');
  }

  public function getDescription() {
    return pht('Configure Paste.');
  }

  public function getFontIcon() {
    return 'fa-paste';
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'metamta.paste.public-create-email',
        'string',
        null)
        ->setLocked(true)
        ->setLockedMessage(pht(
          'This configuration is deprecated. See description for details.'))
        ->setSummary(pht('DEPRECATED - Allow creating pastes via email.'))
        ->setDescription(
          pht(
            'This config has been deprecated in favor of [[ '.
            '/applications/view/PhabricatorPasteApplication/ | '.
            'application settings ]], which allow for multiple email '.
            'addresses and other functionality.')),
      $this->newOption(
        'metamta.paste.subject-prefix',
        'string',
        '[Paste]')
        ->setDescription(pht('Subject prefix for Paste email.')),
    );
  }

}
