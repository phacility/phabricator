<?php

abstract class SlowvoteConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorSlowvoteApplication');
  }

}
