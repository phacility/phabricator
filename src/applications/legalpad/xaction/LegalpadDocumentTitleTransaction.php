<?php

final class LegalpadDocumentTitleTransaction
  extends LegalpadDocumentTransactionType {

  const TRANSACTIONTYPE = 'title';

  public function generateOldValue($object) {
    return $object->getTitle();
  }

  public function applyInternalEffects($object, $value) {
    $object->setTitle($value);
    $body = $object->getDocumentBody();
    $body->setTitle($value);
    $object->attachDocumentBody($body);
  }

  public function getTitle() {
    $old = $this->getOldValue();

    if (!strlen($old)) {
      return pht(
        '%s created this document.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s renamed this document from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();

    if (!strlen($old)) {
      return pht(
        '%s created %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s renamed %s from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getTitle(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Documents must have a title.'));
    }

    $max_length = $object->getColumnMaximumByteLength('title');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht('The title can be no longer than %s characters.',
          new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }

}
