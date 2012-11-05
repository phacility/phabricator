<?php

abstract class PhabricatorFactManagementWorkflow
  extends PhutilArgumentWorkflow {

  public function isExecutable() {
    return true;
  }

}
