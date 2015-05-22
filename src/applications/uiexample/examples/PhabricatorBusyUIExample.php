<?php

final class PhabricatorBusyUIExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Busy');
  }

  public function getDescription() {
    return pht('Busy.');
  }

  public function renderExample() {
    Javelin::initBehavior('phabricator-busy-example');
    return null;
  }
}
