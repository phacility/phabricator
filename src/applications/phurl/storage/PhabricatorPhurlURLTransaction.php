<?php

final class PhabricatorPhurlURLTransaction
  extends PhabricatorModularTransaction {

  const MAILTAG_DETAILS = 'phurl-details';

  public function getApplicationName() {
    return 'phurl';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPhurlURLPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorPhurlURLTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorPhurlURLTransactionType';
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    switch ($this->getTransactionType()) {
      case PhabricatorPhurlURLNameTransaction::TRANSACTIONTYPE:
      case PhabricatorPhurlURLLongURLTransaction::TRANSACTIONTYPE:
      case PhabricatorPhurlURLAliasTransaction::TRANSACTIONTYPE:
      case PhabricatorPhurlURLDescriptionTransaction::TRANSACTIONTYPE:
        $phids[] = $this->getObjectPHID();
        break;
    }

    return $phids;
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case PhabricatorPhurlURLNameTransaction::TRANSACTIONTYPE:
      case PhabricatorPhurlURLLongURLTransaction::TRANSACTIONTYPE:
      case PhabricatorPhurlURLAliasTransaction::TRANSACTIONTYPE:
      case PhabricatorPhurlURLDescriptionTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_DETAILS;
        break;
    }
    return $tags;
  }

}
