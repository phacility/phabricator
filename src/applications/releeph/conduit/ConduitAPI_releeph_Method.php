<?php

abstract class ConduitAPI_releeph_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorApplicationReleeph');
  }

}
