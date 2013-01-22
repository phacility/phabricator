<?php

abstract class PhabricatorConfigManagementWorkflow
  extends PhutilArgumentWorkflow {

  final public function isExecutable() {
    return true;
  }

}
