<?php

final class PhabricatorPholioConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Pholio');
  }

  public function getDescription() {
    return pht('Configure Pholio.');
  }

  public function getFontIcon() {
    return 'fa-camera-retro';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    return array(
      $this->newOption('metamta.pholio.subject-prefix', 'string', '[Pholio]')
        ->setDescription(pht('Subject prefix for Pholio email.')),
    );
  }

}
