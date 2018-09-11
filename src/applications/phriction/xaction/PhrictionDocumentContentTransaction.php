<?php

final class PhrictionDocumentContentTransaction
  extends PhrictionDocumentEditTransaction {

  const TRANSACTIONTYPE = 'content';

  public function applyInternalEffects($object, $value) {
    parent::applyInternalEffects($object, $value);

    $object->setStatus(PhrictionDocumentStatus::STATUS_EXISTS);

    $this->getEditor()->setShouldPublishContent($object, true);
  }

}
