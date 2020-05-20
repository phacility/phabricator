<?php

final class PhrictionDocumentMoveToTransaction
  extends PhrictionDocumentVersionTransaction {

  const TRANSACTIONTYPE = 'move-to';

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

    $editor = $this->getEditor();
    $editor->setMoveAwayDocument($document);

    return $dict;
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus(PhrictionDocumentStatus::STATUS_EXISTS);

    $content = $this->getNewDocumentContent($object);

    $content->setContent($value['content']);
    $content->setTitle($value['title']);
    $content->setChangeType(PhrictionChangeType::CHANGE_MOVE_HERE);
    $content->setChangeRef($value['id']);
  }

  public function getActionStrength() {
    return 100;
  }

  public function getActionName() {
    return pht('Moved');
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    return pht(
      '%s moved this document from %s.',
      $this->renderAuthor(),
      $this->renderHandle($new['phid']));
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    return pht(
      '%s moved %s from %s',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderHandle($new['phid']));
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $e_text = null;
    foreach ($xactions as $xaction) {
      $source_document = $xaction->getNewValue();
      switch ($source_document->getStatus()) {
        case PhrictionDocumentStatus::STATUS_DELETED:
          $e_text = pht('A deleted document can not be moved.');
          break;
        case PhrictionDocumentStatus::STATUS_MOVED:
          $e_text = pht('A moved document can not be moved again.');
          break;
        case PhrictionDocumentStatus::STATUS_STUB:
          $e_text = pht('A stub document can not be moved.');
          break;
        default:
          $e_text = null;
          break;
      }

      if ($e_text !== null) {
        $errors[] = $this->newInvalidError($e_text);
      }

    }

    // TODO: Move Ancestry validation here once all types are converted.

    return $errors;
  }

  public function getIcon() {
    return 'fa-arrows';
  }

  public function shouldHideForFeed() {
    return true;
  }

}
