<?php

final class PhabricatorFileTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME  = 'file:name';

  public function getApplicationName() {
    return 'file';
  }

  public function getApplicationTransactionType() {
    return PhabricatorFileFilePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorFileTransactionComment();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        return pht(
          '%s updated the name for this file from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
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
      case self::TYPE_NAME:
        return pht(
          '%s updated the name of %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid),
          $old,
          $new);
        break;
      }

    return parent::getTitleForFeed();
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        return 'fa-pencil';
    }

    return parent::getIcon();
  }


  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        return PhabricatorTransactions::COLOR_BLUE;
    }

    return parent::getColor();
  }
}
