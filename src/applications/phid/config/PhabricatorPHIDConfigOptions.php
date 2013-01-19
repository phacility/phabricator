<?php

final class PhabricatorPHIDConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("PHID");
  }

  public function getDescription() {
    return pht("Configure PHID generation and lookup.");
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'phid.external-loaders',
        'wild',
        null)
        ->setDescription(
          pht(
            'For each new 4-char PHID type, point to an external loader for '.
            'that type.')),
    );
  }

}
