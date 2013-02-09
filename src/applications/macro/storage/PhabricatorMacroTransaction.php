<?php

final class PhabricatorMacroTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'file';
  }

  public function getTableName() {
    return 'macro_transaction';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPHIDConstants::PHID_TYPE_MCRO;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorMacroTransactionComment();
  }

  public function getApplicationObjectTypeName() {
    return pht('macro');
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_FILE:
        if ($old !== null) {
          $phids[] = $old;
        }
        $phids[] = $new;
        break;
    }

    return $phids;
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_NAME:
        return ($old === null);
    }

    return parent::shouldHide();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_NAME:
        return pht(
          '%s renamed this macro from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
        break;
      case PhabricatorMacroTransactionType::TYPE_DISABLED:
        if ($new) {
          return pht(
            '%s disabled this macro.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s restored this macro.',
            $this->renderHandleLink($author_phid));
        }
        break;
      case PhabricatorMacroTransactionType::TYPE_FILE:
        if ($old === null) {
          return pht(
            '%s created this macro.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s changed the image for this macro from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
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
      case PhabricatorMacroTransactionType::TYPE_NAME:
        return pht(
          '%s renamed %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid),
          $old,
          $new);
      case PhabricatorMacroTransactionType::TYPE_DISABLED:
        if ($new) {
          return pht(
            '%s disabled %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s restored %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
      case PhabricatorMacroTransactionType::TYPE_FILE:
        if ($old === null) {
          return pht(
            '%s created %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s updated the image for %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
    }

    return parent::getTitleForFeed();
  }

  public function getActionName() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_NAME:
        if ($old === null) {
          return pht('Created');
        } else {
          return pht('Renamed');
        }
      case PhabricatorMacroTransactionType::TYPE_DISABLED:
        if ($new) {
          return pht('Disabled');
        } else {
          return pht('Restored');
        }
      case PhabricatorMacroTransactionType::TYPE_FILE:
        if ($old === null) {
          return pht('Created');
        } else {
          return pht('Edited Image');
        }
    }

    return parent::getActionName();
  }

  public function getActionStrength() {
    switch ($this->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_DISABLED:
        return 2.0;
      case PhabricatorMacroTransactionType::TYPE_FILE:
        return 1.5;
    }
    return parent::getActionStrength();
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_NAME:
        return 'edit';
      case PhabricatorMacroTransactionType::TYPE_FILE:
        if ($old === null) {
          return 'create';
        } else {
          return 'edit';
        }
      case PhabricatorMacroTransactionType::TYPE_DISABLED:
        if ($new) {
          return 'delete';
        } else {
          return 'undo';
        }
    }

    return parent::getIcon();
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_NAME:
        return PhabricatorTransactions::COLOR_BLUE;
      case PhabricatorMacroTransactionType::TYPE_FILE:
        if ($old === null) {
          return PhabricatorTransactions::COLOR_GREEN;
        } else {
          return PhabricatorTransactions::COLOR_BLUE;
        }
      case PhabricatorMacroTransactionType::TYPE_DISABLED:
        if ($new) {
          return PhabricatorTransactions::COLOR_BLACK;
        } else {
          return PhabricatorTransactions::COLOR_SKY;
        }
    }

    return parent::getColor();
  }


}

