<?php

abstract class ConduitAPI_owners_Method
  extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationOwners');
  }

}
