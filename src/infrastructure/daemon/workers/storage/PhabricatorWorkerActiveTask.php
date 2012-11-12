<?php

final class PhabricatorWorkerActiveTask extends PhabricatorWorkerTask {

  private $serverTime;
  private $localTime;

  public function getTableName() {
    return 'worker_task';
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_IDS => self::IDS_COUNTER,
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function setServerTime($server_time) {
    $this->serverTime = $server_time;
    $this->localTime = time();
    return $this;
  }

  public function setLeaseDuration($lease_duration) {
    $this->checkLease();
    $server_lease_expires = $this->serverTime + $lease_duration;
    $this->setLeaseExpires($server_lease_expires);

    // NOTE: This is primarily to allow unit tests to set negative lease
    // durations so they don't have to wait around for leases to expire. We
    // check that the lease is valid above.
    return $this->forceSaveWithoutLease();
  }

  public function save() {
    $this->checkLease();
    return $this->forceSaveWithoutLease();
  }

  public function forceSaveWithoutLease() {
    $is_new = !$this->getID();
    if ($is_new) {
      $this->failureCount = 0;
    }

    if ($is_new && ($this->getData() !== null)) {
      $data = new PhabricatorWorkerTaskData();
      $data->setData($this->getData());
      $data->save();

      $this->setDataID($data->getID());
    }

    return parent::save();
  }

  protected function checkLease() {
    if ($this->leaseOwner) {
      $current_server_time = $this->serverTime + (time() - $this->localTime);
      if ($current_server_time >= $this->leaseExpires) {
        throw new Exception("Trying to update task after lease expiration!");
      }
    }
  }

  public function delete() {
    throw new Exception(
      "Active tasks can not be deleted directly. ".
      "Use archiveTask() to move tasks to the archive.");
  }

  public function archiveTask($result, $duration) {
    if (!$this->getID()) {
      throw new Exception(
        "Attempting to archive a task which hasn't been save()d!");
    }

    $this->checkLease();

    $archive = id(new PhabricatorWorkerArchiveTask())
      ->setID($this->getID())
      ->setTaskClass($this->getTaskClass())
      ->setLeaseOwner($this->getLeaseOwner())
      ->setLeaseExpires($this->getLeaseExpires())
      ->setFailureCount($this->getFailureCount())
      ->setDataID($this->getDataID())
      ->setResult($result)
      ->setDuration($duration);

    // NOTE: This deletes the active task (this object)!
    $archive->save();

    return $archive;
  }

  public function executeTask() {
    // We do this outside of the try .. catch because we don't have permission
    // to release the lease otherwise.
    $this->checkLease();

    try {
      $id = $this->getID();
      $class = $this->getTaskClass();

      if (!class_exists($class)) {
        throw new PhabricatorWorkerPermanentFailureException(
          "Task class '{$class}' does not exist!");
      }

      if (!is_subclass_of($class, 'PhabricatorWorker')) {
        throw new PhabricatorWorkerPermanentFailureException(
          "Task class '{$class}' does not extend PhabricatorWorker.");
      }

      $worker = newv($class, array($this->getData()));

      $maximum_failures = $worker->getMaximumRetryCount();
      if ($maximum_failures !== null) {
        if ($this->getFailureCount() > $maximum_failures) {
          throw new PhabricatorWorkerPermanentFailureException(
            "Task {$id} has exceeded the maximum number of failures ".
            "({$maximum_failures}).");
        }
      }

      $lease = $worker->getRequiredLeaseTime();
      if ($lease !== null) {
        $this->setLeaseDuration($lease);
      }

      $t_start = microtime(true);
        $worker->executeTask();
      $t_end = microtime(true);
      $duration = (int)(1000000 * ($t_end - $t_start));

      $result = $this->archiveTask(
        PhabricatorWorkerArchiveTask::RESULT_SUCCESS,
        $duration);
    } catch (PhabricatorWorkerPermanentFailureException $ex) {
      $result = $this->archiveTask(
        PhabricatorWorkerArchiveTask::RESULT_FAILURE,
        0);
      $result->setExecutionException($ex);
    } catch (Exception $ex) {
      $this->setExecutionException($ex);
      $this->setFailureCount($this->getFailureCount() + 1);

      $retry = $worker->getWaitBeforeRetry($this);
      $retry = coalesce(
        $retry,
        PhabricatorWorkerLeaseQuery::DEFAULT_LEASE_DURATION);

      // NOTE: As a side effect, this saves the object.
      $this->setLeaseDuration($retry);

      $result = $this;
    }

    return $result;
  }


}
