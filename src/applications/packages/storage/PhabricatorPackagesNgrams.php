<?php

abstract class PhabricatorPackagesNgrams
  extends PhabricatorSearchNgrams {

  public function getApplicationName() {
    return 'packages';
  }

}
