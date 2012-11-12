<?php

abstract class PhabricatorRepositoryManagementWorkflow
  extends PhutilArgumentWorkflow {

  public function isExecutable() {
    return true;
  }

}
