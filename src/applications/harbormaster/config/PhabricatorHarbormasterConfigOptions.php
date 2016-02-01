<?php

final class PhabricatorHarbormasterConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Harbormaster');
  }

  public function getDescription() {
    return pht('Configure Harbormaster build engine.');
  }

  public function getIcon() {
    return 'fa-ship';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    return array();
  }

}
