<?php

final class DifferentialDiffTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_DIFF_CREATE = 'differential:diff:create';

  public function getApplicationName() {
    return 'differential';
  }

  public function getApplicationTransactionType() {
    return DifferentialDiffPHIDType::TYPECONST;
  }

  public function shouldHideForMail(array $xactions) {
    return true;
  }

  public function getActionName() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DIFF_CREATE;
        return pht('Created');
    }

    return parent::getActionName();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();
    $author_handle = $this->renderHandleLink($author_phid);

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_DIFF_CREATE;
        return pht(
          '%s created this diff.',
          $author_handle);
    }

    return parent::getTitle();
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DIFF_CREATE:
        return 'fa-refresh';
    }

    return parent::getIcon();
  }

  public function getColor() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DIFF_CREATE:
        return PhabricatorTransactions::COLOR_SKY;
    }

    return parent::getColor();
  }

}
