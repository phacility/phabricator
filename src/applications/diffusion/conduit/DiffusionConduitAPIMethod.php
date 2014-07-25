<?php

abstract class DiffusionConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorDiffusionApplication');
  }

}
