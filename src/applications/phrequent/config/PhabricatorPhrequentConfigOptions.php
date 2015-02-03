<?php

final class PhabricatorPhrequentConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Phrequent');
  }

  public function getDescription() {
    return pht('Configure Phrequent.');
  }

  public function getFontIcon() {
    return 'fa-clock-o';
  }

  public function getOptions() {
    return array();
  }

}
