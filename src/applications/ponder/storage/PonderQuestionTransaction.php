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
    return PonderQuestionPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PonderQuestionTransactionComment();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    switch ($this->getTransactionType()) {
      case self::TYPE_ANSWERS:
        $phids[] = $this->getNewAnswerPHID();
        $phids[] = $this->getObjectPHID();
        break;
    }

    return $phids;
  }

  public function getRemarkupBlocks() {
    $blocks = parent::getRemarkupBlocks();

    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        $blocks[] = $this->getNewValue();
        break;
    }

    return $blocks;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

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
        $answer_handle = $this->getHandle($this->getNewAnswerPHID());
        $question_handle = $this->getHandle($object_phid);

        return pht(
          '%s answered %s',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
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
        return 'fa-pencil';
      case self::TYPE_STATUS:
        switch ($new) {
          case PonderQuestionStatus::STATUS_OPEN:
            return 'fa-check-circle';
          case PonderQuestionStatus::STATUS_CLOSED:
            return 'fa-minus-circle';
        }
      case self::TYPE_ANSWERS:
        return 'fa-plus';
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
            return PhabricatorTransactions::COLOR_INDIGO;
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
    return $this->renderTextCorpusChangeDetails(
      $viewer,
      $this->getOldValue(),
      $this->getNewValue());
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
        $answer_handle = $this->getHandle($this->getNewAnswerPHID());
        $question_handle = $this->getHandle($object_phid);
        return pht(
          '%s answered %s',
          $this->renderHandleLink($author_phid),
          $answer_handle->renderLink($question_handle->getFullName()));
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

    return parent::getTitleForFeed();
  }

  public function getBodyForFeed(PhabricatorFeedStory $story) {
    $new = $this->getNewValue();
    $old = $this->getOldValue();

    $body = null;

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          $question = $story->getObject($this->getObjectPHID());
          return phutil_escape_html_newlines(
            id(new PhutilUTF8StringTruncator())
            ->setMaximumGlyphs(128)
            ->truncateString($question->getContent()));
        }
        break;
      case self::TYPE_ANSWERS:
        $answer = $this->getNewAnswerObject($story);
        if ($answer) {
          return phutil_escape_html_newlines(
            id(new PhutilUTF8StringTruncator())
            ->setMaximumGlyphs(128)
            ->truncateString($answer->getContent()));
        }
        break;
    }

    return parent::getBodyForFeed($story);
  }

  /**
   * Currently the application only supports adding answers one at a time.
   * This data is stored as a list of phids. Use this function to get the
   * new phid.
   */
  private function getNewAnswerPHID() {
    $new = $this->getNewValue();
    $old = $this->getOldValue();
    $add = array_diff($new, $old);

    if (count($add) != 1) {
      throw new Exception(
        pht('There should be only one answer added at a time.'));
    }

    return reset($add);
  }

  /**
   * Generally, the answer object is only available if the transaction
   * type is `self::TYPE_ANSWERS`.
   *
   * Some stories - notably ones made before D7027 - will be of the more
   * generic @{class:PhabricatorApplicationTransactionFeedStory}. These
   * poor stories won't have the PonderAnswer loaded, and thus will have
   * less cool information.
   */
  private function getNewAnswerObject(PhabricatorFeedStory $story) {
    if ($story instanceof PonderTransactionFeedStory) {
      $answer_phid = $this->getNewAnswerPHID();
      if ($answer_phid) {
        return $story->getObject($answer_phid);
      }
    }
    return null;
  }

}
