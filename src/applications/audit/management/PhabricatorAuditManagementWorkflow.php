<?php

abstract class PhabricatorAuditManagementWorkflow
  extends PhutilArgumentWorkflow {

  public function isExecutable() {
    return true;
  }

}
