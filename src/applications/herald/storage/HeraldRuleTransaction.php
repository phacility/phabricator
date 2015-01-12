<?php

final class HeraldRuleTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_EDIT = 'herald:edit';
  const TYPE_DISABLE = 'herald:disable';

  public function getApplicationName() {
    return 'herald';
  }

  public function getApplicationTransactionType() {
    return HeraldRulePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new HeraldRuleTransactionComment();
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_DISABLE:
        if ($new) {
          return 'red';
        } else {
          return 'green';
        }
    }

    return parent::getColor();
  }

  public function getActionName() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_DISABLE:
        if ($new) {
          return pht('Disabled');
        } else {
          return pht('Enabled');
        }
    }

    return parent::getActionName();
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_DISABLE:
        if ($new) {
          return 'fa-ban';
        } else {
          return 'fa-check';
        }
    }

    return parent::getIcon();
  }


  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_DISABLE:
        if ($new) {
          return pht(
            '%s disabled this rule.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s enabled this rule.',
            $this->renderHandleLink($author_phid));
        }
    }

    return parent::getTitle();
  }

}
