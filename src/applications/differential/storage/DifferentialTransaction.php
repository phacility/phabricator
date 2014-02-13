<?php

final class DifferentialTransaction extends PhabricatorApplicationTransaction {

  const TYPE_INLINE = 'differential:inline';
  const TYPE_UPDATE = 'differential:update';
  const TYPE_ACTION = 'differential:action';

  public function getApplicationName() {
    return 'differential';
  }

  public function getApplicationTransactionType() {
    return DifferentialPHIDTypeRevision::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new DifferentialTransactionComment();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();
    $author_handle = $this->renderHandleLink($author_phid);

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_INLINE:
        return pht(
          '%s added inline comments.',
          $author_handle);
      case self::TYPE_UPDATE:
        if ($new) {
          // TODO: Migrate to PHIDs and use handles here?
          // TODO: Link this?
          return pht(
            '%s updated this revision to Diff #%d.',
            $author_handle,
            $new);
        } else {
          return pht(
            '%s updated this revision.',
            $author_handle);
        }
      case self::TYPE_ACTION:
        return DifferentialAction::getBasicStoryText($new, $author_handle);
    }

    return parent::getTitle();
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case self::TYPE_INLINE:
        return 'comment';
      case self::TYPE_UPDATE:
        return 'refresh';
      case self::TYPE_ACTION:
        switch ($this->getNewValue()) {
          case DifferentialAction::ACTION_CLOSE:
            return 'ok';
          case DifferentialAction::ACTION_ACCEPT:
            return 'enable';
          case DifferentialAction::ACTION_REJECT:
          case DifferentialAction::ACTION_ABANDON:
            return 'delete';
          case DifferentialAction::ACTION_RETHINK:
            return 'disable';
          case DifferentialAction::ACTION_REQUEST:
            return 'refresh';
          case DifferentialAction::ACTION_RECLAIM:
          case DifferentialAction::ACTION_REOPEN:
            return 'new';
          case DifferentialAction::ACTION_RESIGN:
            return 'undo';
          case DifferentialAction::ACTION_CLAIM:
            return 'user';
        }
    }

    return parent::getIcon();
  }

  public function getColor() {
    switch ($this->getTransactionType()) {
      case self::TYPE_UPDATE:
        return PhabricatorTransactions::COLOR_SKY;
      case self::TYPE_ACTION:
        switch ($this->getNewValue()) {
          case DifferentialAction::ACTION_CLOSE:
            return PhabricatorTransactions::COLOR_BLUE;
          case DifferentialAction::ACTION_ACCEPT:
            return PhabricatorTransactions::COLOR_GREEN;
          case DifferentialAction::ACTION_REJECT:
            return PhabricatorTransactions::COLOR_RED;
          case DifferentialAction::ACTION_ABANDON:
            return PhabricatorTransactions::COLOR_BLACK;
          case DifferentialAction::ACTION_RETHINK:
            return PhabricatorTransactions::COLOR_RED;
          case DifferentialAction::ACTION_REQUEST:
            return PhabricatorTransactions::COLOR_SKY;
          case DifferentialAction::ACTION_RECLAIM:
            return PhabricatorTransactions::COLOR_SKY;
          case DifferentialAction::ACTION_REOPEN:
            return PhabricatorTransactions::COLOR_SKY;
          case DifferentialAction::ACTION_RESIGN:
            return PhabricatorTransactions::COLOR_ORANGE;
          case DifferentialAction::ACTION_CLAIM:
            return PhabricatorTransactions::COLOR_YELLOW;
        }
    }


    return parent::getColor();
  }

}
