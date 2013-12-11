<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_phragment_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationPhragment');
  }

}
