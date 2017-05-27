<?php

final class PhrictionDocumentDeleteTransaction
  extends PhrictionDocumentTransactionType {

  const TRANSACTIONTYPE = 'delete';

  public function generateOldValue($object) {
    return null;
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus(PhrictionDocumentStatus::STATUS_DELETED);
  }

  public function applyExternalEffects($object, $value) {
    $this->getEditor()->getNewContent()->setContent('');
    $this->getEditor()->getNewContent()->setChangeType(
      PhrictionChangeType::CHANGE_DELETE);
  }

  public function getActionStrength() {
    return 1.5;
  }

  public function getActionName() {
    return pht('Deleted');
  }

  public function getTitle() {
    return pht(
      '%s deleted this document.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s deleted %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $e_text = null;
    foreach ($xactions as $xaction) {
      switch ($object->getStatus()) {
        case PhrictionDocumentStatus::STATUS_DELETED:
          if ($xaction->getMetadataValue('contentDelete')) {
            $e_text = pht(
              'This document is already deleted. You must specify '.
              'content to re-create the document and make further edits.');
          } else {
            $e_text = pht(
              'An already deleted document can not be deleted.');
          }
          break;
        case PhrictionDocumentStatus::STATUS_MOVED:
          $e_text = pht('A moved document can not be deleted.');
          break;
        case PhrictionDocumentStatus::STATUS_STUB:
          $e_text = pht('A stub document can not be deleted.');
          break;
        default:
          break;
      }

      if ($e_text !== null) {
        $errors[] = $this->newInvalidError($e_text);
      }

    }

    return $errors;
  }

  public function getIcon() {
    return 'fa-trash-o';
  }

  public function getColor() {
    return 'red';
  }

}
