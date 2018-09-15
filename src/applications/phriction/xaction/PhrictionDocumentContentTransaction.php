<?php

final class PhrictionDocumentContentTransaction
  extends PhrictionDocumentEditTransaction {

  const TRANSACTIONTYPE = 'content';

  public function applyInternalEffects($object, $value) {
    parent::applyInternalEffects($object, $value);

    $object->setStatus(PhrictionDocumentStatus::STATUS_EXISTS);

    $this->getEditor()->setShouldPublishContent($object, true);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    // NOTE: This is slightly different from the draft validation. Here,
    // we're validating that: you can't edit away a document; and you can't
    // create an empty document.

    $content = $object->getContent()->getContent();
    if ($this->isEmptyTextTransaction($content, $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Documents must have content.'));
    }

    return $errors;
  }

}
