<?php

final class PhabricatorOwnersConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Owners');
  }

  public function getDescription() {
    return pht('Configure Owners.');
  }

  public function getFontIcon() {
    return 'fa-gift';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    return array(
      $this->newOption('metamta.package.subject-prefix', 'string', '[Package]')
        ->setDescription(pht('Subject prefix for Owners email.')),
    );
  }

}
