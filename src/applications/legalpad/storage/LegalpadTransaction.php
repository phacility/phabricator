<?php

final class LegalpadTransaction extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'legalpad';
  }

  public function getApplicationTransactionType() {
    return PhabricatorLegalpadDocumentPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new LegalpadTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'LegalpadDocumentTransactionType';
  }

}
