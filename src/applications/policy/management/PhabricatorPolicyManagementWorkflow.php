<?php

abstract class PhabricatorPolicyManagementWorkflow
  extends PhutilArgumentWorkflow {

  final public function isExecutable() {
    return true;
  }

}
