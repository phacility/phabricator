<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_diffusion_Method
  extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationDiffusion');
  }

}
