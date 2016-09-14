<?php

abstract class PhabricatorPackagesEditEngine
  extends PhabricatorEditEngine {

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorPackagesApplication';
  }

}
