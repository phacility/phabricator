<?php

final class PhabricatorSearchWorker extends PhabricatorWorker {

  public function doWork() {
    $data = $this->getTaskData();

    $phid = idx($data, 'documentPHID');
    $context = idx($data, 'context');

    id(new PhabricatorSearchIndexer())
      ->indexDocumentByPHID($phid, $context);
  }

}
