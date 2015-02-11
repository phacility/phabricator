<?php

final class PhabricatorSearchWorker extends PhabricatorWorker {

  protected function doWork() {
    $data = $this->getTaskData();

    $phid = idx($data, 'documentPHID');
    $context = idx($data, 'context');

    id(new PhabricatorSearchIndexer())
      ->indexDocumentByPHID($phid, $context);
  }

}
