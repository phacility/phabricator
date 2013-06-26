<?php

abstract class PhabricatorFeedManagementWorkflow
  extends PhutilArgumentWorkflow {

  final public function isExecutable() {
    return true;
  }

}
