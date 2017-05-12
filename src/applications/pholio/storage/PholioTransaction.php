<?php

final class PholioTransaction extends PhabricatorModularTransaction {

  // Edits to images within the mock
  const TYPE_IMAGE_FILE = 'image-file';
  const TYPE_IMAGE_REPLACE = 'image-replace';
  const TYPE_IMAGE_SEQUENCE = 'image-sequence';

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

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();
    $phids[] = $this->getObjectPHID();

    $new = $this->getNewValue();
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_IMAGE_FILE:
        $phids = array_merge($phids, $new, $old);
        break;
      case self::TYPE_IMAGE_REPLACE:
        $phids[] = $new;
        $phids[] = $old;
        break;
      case PholioImageDescriptionTransaction::TRANSACTIONTYPE:
      case PholioImageNameTransaction::TRANSACTIONTYPE:
      case self::TYPE_IMAGE_SEQUENCE:
        $phids[] = key($new);
        break;
    }

    return $phids;
  }

  public function shouldHide() {
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      // this is boring / silly to surface; changing sequence is NBD
      case self::TYPE_IMAGE_SEQUENCE:
        return true;
    }

    return parent::shouldHide();
  }

  public function getIcon() {

    $new = $this->getNewValue();
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_INLINE:
        return 'fa-comment';
      case self::TYPE_IMAGE_SEQUENCE:
        return 'fa-pencil';
      case self::TYPE_IMAGE_FILE:
      case self::TYPE_IMAGE_REPLACE:
        return 'fa-picture-o';
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
      case self::TYPE_IMAGE_SEQUENCE:
      case self::TYPE_IMAGE_FILE:
      case self::TYPE_IMAGE_REPLACE:
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
      case self::TYPE_IMAGE_REPLACE:
        return pht(
          '%s replaced %s with %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($old),
          $this->renderHandleLink($new));
        break;
      case self::TYPE_IMAGE_FILE:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ($add && $rem) {
          return pht(
            '%s edited image(s), added %d: %s; removed %d: %s.',
            $this->renderHandleLink($author_phid),
            count($add),
            $this->renderHandleList($add),
            count($rem),
            $this->renderHandleList($rem));
        } else if ($add) {
          return pht(
            '%s added %d image(s): %s.',
            $this->renderHandleLink($author_phid),
            count($add),
            $this->renderHandleList($add));
        } else {
          return pht(
            '%s removed %d image(s): %s.',
            $this->renderHandleLink($author_phid),
            count($rem),
            $this->renderHandleList($rem));
        }
        break;
      case self::TYPE_IMAGE_SEQUENCE:
        return pht(
          '%s updated an image\'s (%s) sequence.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink(key($new)));
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
      case self::TYPE_IMAGE_REPLACE:
      case self::TYPE_IMAGE_FILE:
        return pht(
          '%s updated images of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case self::TYPE_IMAGE_SEQUENCE:
        return pht(
          '%s updated image sequence of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
    }

    return parent::getTitleForFeed();
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_IMAGE_REPLACE:
        return PhabricatorTransactions::COLOR_YELLOW;
      case self::TYPE_IMAGE_FILE:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);
        if ($add && $rem) {
          return PhabricatorTransactions::COLOR_YELLOW;
        } else if ($add) {
          return PhabricatorTransactions::COLOR_GREEN;
        } else {
          return PhabricatorTransactions::COLOR_RED;
        }
    }

    return parent::getColor();
  }

}
