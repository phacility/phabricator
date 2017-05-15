<?php

final class PholioTransaction extends PhabricatorModularTransaction {

  // Your witty commentary at the mock : image : x,y level
  const TYPE_INLINE  = 'inline';

  const MAILTAG_STATUS            = 'pholio-status';
  const MAILTAG_COMMENT           = 'pholio-comment';
  const MAILTAG_UPDATED           = 'pholio-updated';
  const MAILTAG_OTHER             = 'pholio-other';

  public function getApplicationName() {
    return 'pholio';
  }

  public function getBaseTransactionClass() {
    return 'PholioTransactionType';
  }

  public function getApplicationTransactionType() {
    return PholioMockPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PholioTransactionComment();
  }

  public function getApplicationTransactionViewObject() {
    return new PholioTransactionView();
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case self::TYPE_INLINE:
        return 'fa-comment';
    }

    return parent::getIcon();
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case self::TYPE_INLINE:
      case PhabricatorTransactions::TYPE_COMMENT:
        $tags[] = self::MAILTAG_COMMENT;
        break;
      case PholioMockStatusTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_STATUS;
        break;
      case PholioMockNameTransaction::TRANSACTIONTYPE:
      case PholioMockDescriptionTransaction::TRANSACTIONTYPE:
      case PholioImageNameTransaction::TRANSACTIONTYPE:
      case PholioImageDescriptionTransaction::TRANSACTIONTYPE:
      case PholioImageSequenceTransaction::TRANSACTIONTYPE:
      case PholioImageFileTransaction::TRANSACTIONTYPE:
      case PholioImageReplaceTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_UPDATED;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_INLINE:
        $count = 1;
        foreach ($this->getTransactionGroup() as $xaction) {
          if ($xaction->getTransactionType() == $type) {
            $count++;
          }
        }

        return pht(
          '%s added %d inline comment(s).',
          $this->renderHandleLink($author_phid),
          $count);
        break;
    }

    return parent::getTitle();
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_INLINE:
        return pht(
          '%s added an inline comment to %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
    }

    return parent::getTitleForFeed();
  }

}
