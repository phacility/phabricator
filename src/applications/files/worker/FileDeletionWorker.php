<?php

final class FileDeletionWorker extends PhabricatorWorker {

  private function loadFile() {
    $phid = idx($this->getTaskData(), 'objectPHID');
    if (!$phid) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('No "%s" in task data.', 'objectPHID'));
    }

    $file = id(new PhabricatorFileQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($phid))
      ->executeOne();

    if (!$file) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('File "%s" does not exist.', $phid));
    }

    return $file;
  }

  protected function doWork() {
    $file = $this->loadFile();
    $engine = new PhabricatorDestructionEngine();
    $engine->destroyObject($file);
  }

}
