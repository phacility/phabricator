<?php

final class PhrictionDocumentPublishedHeraldField
  extends PhrictionDocumentHeraldField {

  const FIELDCONST = 'phriction.document.published';

  public function getHeraldFieldName() {
    return pht('Published document changed');
  }

  public function getFieldGroupKey() {
    return HeraldTransactionsFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    // The published document changes if we apply a "publish" transaction
    // (which points the published document pointer at new content) or if we
    // apply a "content" transaction.

    // When a change affects only the draft document, it applies as a "draft"
    // transaction.

    $type_content = PhrictionDocumentContentTransaction::TRANSACTIONTYPE;
    $type_publish = PhrictionDocumentPublishTransaction::TRANSACTIONTYPE;

    if ($this->hasAppliedTransactionOfType($type_content)) {
      return true;
    }

    if ($this->hasAppliedTransactionOfType($type_publish)) {
      return true;
    }

    return false;
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_BOOL;
  }

}
