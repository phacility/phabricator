<?php

final class PonderQuestionTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_TITLE = 'ponder.question:question';
  const TYPE_CONTENT = 'ponder.question:content';
  const TYPE_ANSWERS = 'ponder.question:answer';
  const TYPE_STATUS = 'ponder.question:status';

  public function getApplicationName() {
    return 'ponder';
  }

  public function getTableName() {
    return 'ponder_questiontransaction';
  }

  public function getApplicationTransactionType() {
    return PonderPHIDTypeQuestion::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PonderQuestionTransactionComment();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return pht(
            '%s asked this question.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s edited the question title from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
      case self::TYPE_CONTENT:
        return pht(
          '%s edited the question description.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_ANSWERS:
        // TODO: This could be richer.
        return pht(
          '%s added an answer.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_STATUS:
        switch ($new) {
          case PonderQuestionStatus::STATUS_OPEN:
            return pht(
              '%s reopened this question.',
              $this->renderHandleLink($author_phid));
          case PonderQuestionStatus::STATUS_CLOSED:
            return pht(
              '%s closed this question.',
              $this->renderHandleLink($author_phid));
        }
    }

    return parent::getTitle();
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
      case self::TYPE_CONTENT:
        return 'edit';
      case self::TYPE_STATUS:
        switch ($new) {
          case PonderQuestionStatus::STATUS_OPEN:
            return 'enable';
          case PonderQuestionStatus::STATUS_CLOSED:
            return 'disable';
        }
      case self::TYPE_ANSWERS:
        return 'new';
    }

    return parent::getIcon();
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
      case self::TYPE_CONTENT:
        return PhabricatorTransactions::COLOR_BLUE;
      case self::TYPE_ANSWERS:
        return PhabricatorTransactions::COLOR_GREEN;
      case self::TYPE_STATUS:
        switch ($new) {
          case PonderQuestionStatus::STATUS_OPEN:
            return PhabricatorTransactions::COLOR_GREEN;
          case PonderQuestionStatus::STATUS_CLOSED:
            return PhabricatorTransactions::COLOR_BLACK;
        }
    }
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        return true;
    }
    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $view = id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setUser($viewer)
      ->setOldText($old)
      ->setNewText($new);

    return $view->render();
  }

  public function getActionStrength() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return 3;
        }
        break;
      case self::TYPE_ANSWERS:
        return 2;
    }

    return parent::getActionStrength();
  }

  public function getActionName() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return pht('Asked');
        }
        break;
      case self::TYPE_ANSWERS:
        return pht('Answered');
    }

    return parent::getActionName();
  }

  public function shouldHide() {
    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        if ($this->getOldValue() === null) {
          return true;
        } else {
          return false;
        }
        break;
    }

    return parent::shouldHide();
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return pht(
            '%s asked a question: %s',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s edited the title of %s (was "%s")',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $old);
        }
      case self::TYPE_CONTENT:
        return pht(
          '%s edited the description of %s',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case self::TYPE_ANSWERS:
        // TODO: This could be richer, too.
        return pht(
          '%s answered %s',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case self::TYPE_STATUS:
        switch ($new) {
          case PonderQuestionStatus::STATUS_OPEN:
            return pht(
              '%s reopened %s',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
          case PonderQuestionStatus::STATUS_CLOSED:
            return pht(
              '%s closed %s',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
        }
    }

    return $this->getTitle();
  }

}

