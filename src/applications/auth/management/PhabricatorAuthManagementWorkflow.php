<?php

abstract class PhabricatorAuthManagementWorkflow
  extends PhutilArgumentWorkflow {

  final public function isExecutable() {
    return true;
  }

}
