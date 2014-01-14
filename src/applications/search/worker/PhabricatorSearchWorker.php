<?php

final class PhabricatorSearchWorker extends PhabricatorWorker {

  public function doWork() {
    $data = $this->getTaskData();
    $phid = idx($data, 'documentPHID');

    id(new PhabricatorSearchIndexer())
      ->indexDocumentByPHID($phid);
  }

}
