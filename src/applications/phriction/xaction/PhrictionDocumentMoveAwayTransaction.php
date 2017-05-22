<?php

final class PhrictionDocumentMoveAwayTransaction
  extends PhrictionDocumentTransactionType {

  const TRANSACTIONTYPE = 'move-away';

  public function generateOldValue($object) {
    return null;
  }

  public function generateNewValue($object, $value) {
    $document = $value;
    $dict = array(
      'id' => $document->getID(),
      'phid' => $document->getPHID(),
      'content' => $document->getContent()->getContent(),
      'title' => $document->getContent()->getTitle(),
    );
    return $dict;
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus(PhrictionDocumentStatus::STATUS_MOVED);
  }

  public function applyExternalEffects($object, $value) {
    $dict = $value;
    $this->getEditor()->getNewContent()->setContent('');
    $this->getEditor()->getNewContent()->setChangeType(
      PhrictionChangeType::CHANGE_MOVE_AWAY);
    $this->getEditor()->getNewContent()->setChangeRef($dict['id']);
  }

  public function getActionName() {
    return pht('Moved Away');
  }

  public function getTitle() {
    $new = $this->getNewValue();

    return pht(
      '%s moved this document to %s',
      $this->renderAuthor(),
      $this->renderHandleLink($new['phid']));
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();

    return pht(
      '%s moved %s to %s',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderHandleLink($new['phid']));
  }

  public function getIcon() {
    return 'fa-arrows';
  }

}
