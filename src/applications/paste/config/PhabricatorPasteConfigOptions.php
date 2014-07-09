<?php

final class PhabricatorPasteConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Paste');
  }

  public function getDescription() {
    return pht('Configure Paste.');
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'metamta.paste.public-create-email',
        'string',
        null)
        ->setDescription(pht('Allow creating pastes via email.')),
      $this->newOption(
        'metamta.paste.subject-prefix',
        'string',
        '[Paste]')
        ->setDescription(pht('Subject prefix for Paste email.'))
    );
  }

}
