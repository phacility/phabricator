<?php

final class PhabricatorPasteConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Paste');
  }

  public function getDescription() {
    return pht('Configure Paste.');
  }

  public function getIcon() {
    return 'fa-paste';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'metamta.paste.subject-prefix',
        'string',
        '[Paste]')
        ->setDescription(pht('Subject prefix for Paste email.')),
    );
  }

}
