<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_slowvote_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationSlowvote');
  }

}
