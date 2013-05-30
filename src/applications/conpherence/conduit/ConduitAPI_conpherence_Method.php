<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_conpherence_Method
  extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationConpherence');
  }

}
