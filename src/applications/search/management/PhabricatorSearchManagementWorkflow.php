<?php

/**
 * @group search
 */
abstract class PhabricatorSearchManagementWorkflow
  extends PhutilArgumentWorkflow {

  final public function isExecutable() {
    return true;
  }

}
