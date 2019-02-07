<?php

final class PhameBlogTransaction
  extends PhabricatorModularTransaction {

  const MAILTAG_DETAILS       = 'phame-blog-details';
  const MAILTAG_SUBSCRIBERS   = 'phame-blog-subscribers';
  const MAILTAG_OTHER         = 'phame-blog-other';

  public function getApplicationName() {
    return 'phame';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPhameBlogPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhameBlogTransactionType';
  }

  public function getMailTags() {
    $tags = parent::getMailTags();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $tags[] = self::MAILTAG_SUBSCRIBERS;
        break;
      case PhameBlogNameTransaction::TRANSACTIONTYPE:
      case PhameBlogSubtitleTransaction::TRANSACTIONTYPE:
      case PhameBlogDescriptionTransaction::TRANSACTIONTYPE:
      case PhameBlogFullDomainTransaction::TRANSACTIONTYPE:
      case PhameBlogParentSiteTransaction::TRANSACTIONTYPE:
      case PhameBlogParentDomainTransaction::TRANSACTIONTYPE:
      case PhameBlogProfileImageTransaction::TRANSACTIONTYPE:
      case PhameBlogHeaderImageTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_DETAILS;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

}
