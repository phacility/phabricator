<?php

final class HarbormasterLogWorker extends HarbormasterWorker {

  protected function doWork() {
    $viewer = $this->getViewer();

    $data = $this->getTaskData();
    $log_phid = idx($data, 'logPHID');

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
      $log = id(new HarbormasterBuildLogQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($log_phid))
        ->executeOne();
      if (!$log) {
        throw new PhabricatorWorkerPermanentFailureException(
          pht(
            'Invalid build log PHID "%s".',
            $log_phid));
      }

      if ($log->getLive()) {
        throw new PhabricatorWorkerPermanentFailureException(
          pht(
            'Log "%s" is still live. Logs can not be finalized until '.
            'they have closed.',
            $log_phid));
      }

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
    $viewer = $this->getViewer();

    $data = $this->getTaskData();
    $is_force = idx($data, 'force');

    if ($log->canCompressLog()) {
      $log->compressLog();
    }

    if ($is_force) {
      $file_phid = $log->getFilePHID();
      if ($file_phid) {
        $file = id(new PhabricatorFileQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($file_phid))
          ->executeOne();
        if ($file) {
          id(new PhabricatorDestructionEngine())
            ->destroyObject($file);
        }
        $log
          ->setFilePHID(null)
          ->save();
      }
    }

    if (!$log->getFilePHID()) {
      $iterator = $log->newChunkIterator()
        ->setAsString(true);

      $source = id(new PhabricatorIteratorFileUploadSource())
        ->setName('harbormaster-log-'.$log->getID().'.log')
        ->setViewPolicy(PhabricatorPolicies::POLICY_NOONE)
        ->setMIMEType('application/octet-stream')
        ->setIterator($iterator);

      $file = $source->uploadFile();

      $file->attachToObject($log->getPHID());

      $log
        ->setFilePHID($file->getPHID())
        ->save();
    }

  }

}
