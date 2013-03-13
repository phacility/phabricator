<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_repository_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationRepository');
  }

}
