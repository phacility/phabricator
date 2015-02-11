<?php

final class ReleephProductTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_ACTIVE = 'releeph:product:active';

  public function getApplicationName() {
    return 'releeph';
  }

  public function getApplicationTransactionType() {
    return ReleephProductPHIDType::TYPECONST;
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_ACTIVE:
        if ($new) {
          return 'green';
        } else {
          return 'black';
        }
        break;
    }

    return parent::getColor();
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_ACTIVE:
        if ($new) {
          return 'fa-pencil';
        } else {
          return 'fa-times';
        }
        break;
    }

    return parent::getIcon();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_ACTIVE:
        if ($new) {
          return pht(
            '%s activated this product.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s deactivated this product.',
            $this->renderHandleLink($author_phid));
        }
        break;
    }

    return parent::getTitle();
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_ACTIVE:
        if ($new) {
          return pht(
            '%s activated release product %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s deactivated release product %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
        break;
    }

    return parent::getTitleForFeed();
  }

  public function getNoEffectDescription() {
    switch ($this->getTransactionType()) {
      case self::TYPE_ACTIVE:
        return pht('The product is already in that state.');
    }

    return parent::getNoEffectDescription();
  }

}
