<?php

final class PhabricatorSlowvoteTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_QUESTION     = 'vote:question';
  const TYPE_DESCRIPTION  = 'vote:description';
  const TYPE_RESPONSES    = 'vote:responses';
  const TYPE_SHUFFLE      = 'vote:shuffle';
  const TYPE_CLOSE        = 'vote:close';

  const MAILTAG_DETAILS = 'vote:details';
  const MAILTAG_RESPONSES = 'vote:responses';
  const MAILTAG_OTHER  = 'vote:vote';

  public function getApplicationName() {
    return 'slowvote';
  }

  public function getApplicationTransactionType() {
    return PhabricatorSlowvotePollPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorSlowvoteTransactionComment();
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
      case self::TYPE_RESPONSES:
      case self::TYPE_SHUFFLE:
      case self::TYPE_CLOSE:
        return ($old === null);
    }

    return parent::shouldHide();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_QUESTION:
        if ($old === null) {
          return pht(
            '%s created this poll.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s changed the poll question from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description for this poll.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_RESPONSES:
        // TODO: This could be more detailed
        return pht(
          '%s changed who can see the responses.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_SHUFFLE:
        if ($new) {
          return pht(
            '%s made poll responses appear in a random order.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s made poll responses appear in a fixed order.',
            $this->renderHandleLink($author_phid));
        }
        break;
      case self::TYPE_CLOSE:
        if ($new) {
          return pht(
            '%s closed this poll.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s reopened this poll.',
            $this->renderHandleLink($author_phid));
        }

        break;
    }

    return parent::getTitle();
  }

  public function getRemarkupBlocks() {
    $blocks = parent::getRemarkupBlocks();

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_DESCRIPTION:
        $blocks[] = $this->getNewValue();
        break;
    }

    return $blocks;
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_QUESTION:
        if ($old === null) {
          return pht(
            '%s created %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));

        } else {
          return pht(
            '%s renamed %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
      break;
      case self::TYPE_DESCRIPTION:
        if ($old === null) {
          return pht(
            '%s set the description of %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));

        } else {
          return pht(
            '%s edited the description of %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
      break;
      case self::TYPE_RESPONSES:
        // TODO: This could be more detailed
        return pht(
          '%s changed who can see the responses of %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));

      case self::TYPE_SHUFFLE:
        if ($new) {
          return pht(
            '%s made %s responses appear in a random order.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));

        } else {
          return pht(
            '%s made %s responses appear in a fixed order.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
        case self::TYPE_CLOSE:
        if ($new) {
          return pht(
            '%s closed %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));

        } else {
          return pht(
            '%s reopened %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
      break;
    }

    return parent::getTitleForFeed();
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_QUESTION:
        if ($old === null) {
          return 'fa-plus';
        } else {
          return 'fa-pencil';
        }
      case self::TYPE_DESCRIPTION:
      case self::TYPE_RESPONSES:
        return 'fa-pencil';
      case self::TYPE_SHUFFLE:
        return 'fa-refresh';
      case self::TYPE_CLOSE:
        if ($new) {
          return 'fa-ban';
        } else {
          return 'fa-pencil';
        }
    }

    return parent::getIcon();
  }


  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_QUESTION:
      case self::TYPE_DESCRIPTION:
      case self::TYPE_RESPONSES:
      case self::TYPE_SHUFFLE:
      case self::TYPE_CLOSE:
        return PhabricatorTransactions::COLOR_BLUE;
    }

    return parent::getColor();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
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

  public function getMailTags() {
    $tags = parent::getMailTags();

    switch ($this->getTransactionType()) {
      case self::TYPE_QUESTION:
      case self::TYPE_DESCRIPTION:
      case self::TYPE_SHUFFLE:
      case self::TYPE_CLOSE:
        $tags[] = self::MAILTAG_DETAILS;
        break;
      case self::TYPE_RESPONSES:
        $tags[] = self::MAILTAG_RESPONSES;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }

    return $tags;
  }


}
