<?php

final class PhabricatorSearchWorker extends PhabricatorWorker {

  public static function queueDocumentForIndexing($phid, $context = null) {
    parent::scheduleTask(
      __CLASS__,
      array(
        'documentPHID' => $phid,
        'context' => $context,
      ),
      array(
        'priority' => parent::PRIORITY_IMPORT,
      ));
  }

  protected function doWork() {
    $data = $this->getTaskData();
    $object_phid = idx($data, 'documentPHID');
    $context = idx($data, 'context');

    $engine = new PhabricatorIndexEngine();

    $key = "index.{$object_phid}";
    $lock = PhabricatorGlobalLock::newLock($key);

    $lock->lock(1);

    try {
      $object = $this->loadObjectForIndexing($object_phid);

      $engine->indexDocumentByPHID($object->getPHID(), $context);

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
