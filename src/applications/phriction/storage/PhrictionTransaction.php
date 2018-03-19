<?php

final class PhrictionTransaction
  extends PhabricatorModularTransaction {

  const MAILTAG_TITLE       = 'phriction-title';
  const MAILTAG_CONTENT     = 'phriction-content';
  const MAILTAG_DELETE      = 'phriction-delete';
  const MAILTAG_SUBSCRIBERS = 'phriction-subscribers';
  const MAILTAG_OTHER       = 'phriction-other';

  public function getApplicationName() {
    return 'phriction';
  }

  public function getApplicationTransactionType() {
    return PhrictionDocumentPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhrictionTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'PhrictionDocumentTransactionType';
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();
    $new = $this->getNewValue();
    switch ($this->getTransactionType()) {
      case PhrictionDocumentMoveToTransaction::TRANSACTIONTYPE:
      case PhrictionDocumentMoveAwayTransaction::TRANSACTIONTYPE:
        $phids[] = $new['phid'];
        break;
      case PhrictionDocumentTitleTransaction::TRANSACTIONTYPE:
        if ($this->getMetadataValue('stub:create:phid')) {
          $phids[] = $this->getMetadataValue('stub:create:phid');
        }
        break;
    }

    return $phids;
  }

  public function shouldHideForMail(array $xactions) {
    switch ($this->getTransactionType()) {
      case PhrictionDocumentMoveToTransaction::TRANSACTIONTYPE:
      case PhrictionDocumentMoveAwayTransaction::TRANSACTIONTYPE:
        return true;
      case PhrictionDocumentTitleTransaction::TRANSACTIONTYPE:
        return $this->getMetadataValue('stub:create:phid', false);
    }
    return parent::shouldHideForMail($xactions);
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case PhrictionDocumentTitleTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_TITLE;
        break;
      case PhrictionDocumentContentTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_CONTENT;
        break;
      case PhrictionDocumentDeleteTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_DELETE;
        break;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $tags[] = self::MAILTAG_SUBSCRIBERS;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

}
