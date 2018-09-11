<?php

final class PhrictionDocumentDraftTransaction
  extends PhrictionDocumentEditTransaction {

  const TRANSACTIONTYPE = 'draft';

  public function applyInternalEffects($object, $value) {
    parent::applyInternalEffects($object, $value);

    $this->getEditor()->setShouldPublishContent($object, false);
  }

}
