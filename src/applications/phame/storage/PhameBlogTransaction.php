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
      case self::TYPE_NAME:
      case self::TYPE_SUBTITLE:
      case self::TYPE_DESCRIPTION:
      case self::TYPE_FULLDOMAIN:
      case self::TYPE_PARENTSITE:
      case self::TYPE_PARENTDOMAIN:
      case self::TYPE_PROFILEIMAGE:
      case self::TYPE_HEADERIMAGE:
        $tags[] = self::MAILTAG_DETAILS;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

}
