<?php

abstract class ConduitAPI_audit_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorApplicationAudit');
  }

}
