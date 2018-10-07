<?php

final class PhrictionDocumentDraftTransaction
  extends PhrictionDocumentEditTransaction {

  const TRANSACTIONTYPE = 'draft';

  public function applyInternalEffects($object, $value) {
    parent::applyInternalEffects($object, $value);

    $this->getEditor()->setShouldPublishContent($object, false);
  }

  public function shouldHideForFeed() {
    return true;
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    // NOTE: We're only validating that you can't edit a document down to
    // nothing in a draft transaction. Alone, this doesn't prevent you from
    // creating a document with no content. The content transaction has
    // validation for that.

    if (!$xactions) {
      return $errors;
    }

    $content = $object->getContent()->getContent();
    if ($this->isEmptyTextTransaction($content, $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Documents must have content.'));
    }

    return $errors;
  }

}
