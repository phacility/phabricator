<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_phame_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorApplicationPhame');
  }

}
