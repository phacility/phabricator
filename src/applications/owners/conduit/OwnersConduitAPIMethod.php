<?php

abstract class OwnersConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorOwnersApplication');
  }

}
