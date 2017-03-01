<?php

abstract class AuditConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorDiffusionApplication');
  }

}
