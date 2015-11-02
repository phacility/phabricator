<?php

final class PhabricatorSearchWorker extends PhabricatorWorker {

  protected function doWork() {
    $data = $this->getTaskData();

    $phid = idx($data, 'documentPHID');
    $context = idx($data, 'context');

    try {
      id(new PhabricatorSearchIndexer())
        ->indexDocumentByPHID($phid, $context);
    } catch (Exception $ex) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Failed to update search index for document "%s": %s',
          $phid,
          $ex->getMessage()));
    }
  }

}
