<?php

abstract class PhameConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorPhameApplication');
  }

}
