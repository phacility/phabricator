<?php

final class PhabricatorFileDeleteTransaction
  extends PhabricatorFileTransactionType {

  const TRANSACTIONTYPE = 'file:delete';

  public function generateOldValue($object) {
    return PhabricatorFile::STATUS_ACTIVE;
  }

  public function applyInternalEffects($object, $value) {
    $file = $object;
    // Mark the file for deletion, save it, and schedule a worker to
    // sweep by later and pick it up.
    $file->setIsDeleted(true);

    PhabricatorWorker::scheduleTask(
      'FileDeletionWorker',
      array('objectPHID' => $file->getPHID()),
      array('priority' => PhabricatorWorker::PRIORITY_BULK));
  }

  public function getIcon() {
    return 'fa-ban';
  }

  public function getColor() {
    return 'red';
  }

  public function getTitle() {
    return pht(
      '%s deleted this file.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s deleted %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
