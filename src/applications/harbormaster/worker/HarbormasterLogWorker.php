<?php

final class HarbormasterLogWorker extends HarbormasterWorker {

  protected function doWork() {
    $viewer = $this->getViewer();

    $data = $this->getTaskData();
    $log_phid = idx($data, 'logPHID');

    $log = id(new HarbormasterBuildLogQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($log_phid))
      ->executeOne();
    if (!$log) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Invalid build log PHID "%s".', $log_phid));
    }

    $phid_key = PhabricatorHash::digestToLength($log_phid, 14);
    $lock_key = "build.log({$phid_key})";
    $lock = PhabricatorGlobalLock::newLock($lock_key);

    try {
      $lock->lock();
    } catch (PhutilLockException $ex) {
      throw new PhabricatorWorkerYieldException(15);
    }

    $caught = null;
    try {
      $this->finalizeBuildLog($log);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $lock->unlock();

    if ($caught) {
      throw $caught;
    }
  }

  private function finalizeBuildLog(HarbormasterBuildLog $log) {
    if ($log->canCompressLog()) {
      $log->compressLog();
    }
  }

}
