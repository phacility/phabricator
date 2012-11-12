<?php

abstract class PhabricatorFilesManagementWorkflow
  extends PhutilArgumentWorkflow {

  public function isExecutable() {
    return true;
  }

}
