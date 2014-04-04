<?php

abstract class PhabricatorManagementWorkflow extends PhutilArgumentWorkflow {

  public function isExecutable() {
    return true;
  }

  public function getViewer() {
    // Some day, we might provide a more general viewer mechanism to scripts.
    // For now, workflows can call this method for convenience and future
    // flexibility.
    return PhabricatorUser::getOmnipotentUser();
  }

}
