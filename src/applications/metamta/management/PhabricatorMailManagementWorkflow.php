<?php

abstract class PhabricatorMailManagementWorkflow
  extends PhutilArgumentWorkflow {

  final public function isExecutable() {
    return true;
  }

}
