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

  protected function parseTimeArgument($time) {
    if (!strlen($time)) {
      return null;
    }

    $epoch = strtotime($time);
    if ($epoch <= 0) {
      throw new PhutilArgumentUsageException(
        pht('Unable to parse time "%s".', $time));
    }
    return $epoch;
  }

  protected function newContentSource() {
    return PhabricatorContentSource::newForSource(
      PhabricatorConsoleContentSource::SOURCECONST);
  }

}
