<?php

final class PhabricatorSearchWorker extends PhabricatorWorker {

  public static function queueDocumentForIndexing($phid, $parameters = null) {
    if ($parameters === null) {
      $parameters = array();
    }

    parent::scheduleTask(
      __CLASS__,
      array(
        'documentPHID' => $phid,
        'parameters' => $parameters,
      ),
      array(
        'priority' => parent::PRIORITY_IMPORT,
        'objectPHID' => $phid,
      ));
  }

  protected function doWork() {
    $data = $this->getTaskData();
    $object_phid = idx($data, 'documentPHID');

    $object = $this->loadObjectForIndexing($object_phid);

    $engine = id(new PhabricatorIndexEngine())
      ->setObject($object);

    $parameters = idx($data, 'parameters', array());
    $engine->setParameters($parameters);

    if (!$engine->shouldIndexObject()) {
      return;
    }

    $key = "index.{$object_phid}";
    $lock = PhabricatorGlobalLock::newLock($key);

    try {
      $lock->lock(1);
    } catch (PhutilLockException $ex) {
      // If we fail to acquire the lock, just yield. It's expected that we may
      // contend on this lock occasionally if a large object receives many
      // updates in a short period of time, and it's appropriate to just retry
      // rebuilding the index later.
      throw new PhabricatorWorkerYieldException(15);
    }

    try {
      // Reload the object now that we have a lock, to make sure we have the
      // most current version.
      $object = $this->loadObjectForIndexing($object->getPHID());

      $engine->setObject($object);

      $engine->indexObject();
    } catch (Exception $ex) {
      $lock->unlock();

      if (!($ex instanceof PhabricatorWorkerPermanentFailureException)) {
        $ex = new PhabricatorWorkerPermanentFailureException(
          pht(
            'Failed to update search index for document "%s": %s',
            $object_phid,
            $ex->getMessage()));
      }

      throw $ex;
    }

    $lock->unlock();
  }

  private function loadObjectForIndexing($phid) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();

    if (!$object) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Unable to load object "%s" to rebuild indexes.',
          $phid));
    }

    return $object;
  }

}
