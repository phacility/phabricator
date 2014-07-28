<?php

abstract class PhragmentConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorPhragmentApplication');
  }

}
