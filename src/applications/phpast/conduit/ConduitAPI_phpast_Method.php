<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_phpast_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationPHPAST');
  }

}
