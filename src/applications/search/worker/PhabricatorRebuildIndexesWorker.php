<?php

final class PhabricatorRebuildIndexesWorker extends PhabricatorWorker {

  public static function rebuildObjectsWithQuery($query_class) {
    parent::scheduleTask(
      __CLASS__,
      array(
        'queryClass' => $query_class,
      ),
      array(
        'priority' => parent::PRIORITY_INDEX,
      ));
  }

  protected function doWork() {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $data = $this->getTaskData();
    $query_class = idx($data, 'queryClass');

    try {
      $query = newv($query_class, array());
    } catch (Exception $ex) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Unable to instantiate query class "%s": %s',
           $query_class,
           $ex->getMessage()));
    }

    $query->setViewer($viewer);

    $iterator = new PhabricatorQueryIterator($query);
    foreach ($iterator as $object) {
      PhabricatorSearchWorker::queueDocumentForIndexing(
        $object->getPHID(),
        array(
          'force' => true,
        ));
    }
  }

}
