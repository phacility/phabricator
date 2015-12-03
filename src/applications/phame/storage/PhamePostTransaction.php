<?php

final class PhamePostTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_TITLE            = 'phame.post.title';
  const TYPE_PHAME_TITLE      = 'phame.post.phame.title';
  const TYPE_BODY             = 'phame.post.body';
  const TYPE_VISIBILITY       = 'phame.post.visibility';

  const MAILTAG_CONTENT       = 'phame-post-content';
  const MAILTAG_COMMENT       = 'phame-post-comment';
  const MAILTAG_OTHER         = 'phame-post-other';

  public function getApplicationName() {
    return 'phame';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPhamePostPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhamePostTransactionComment();
  }

  public function getRemarkupBlocks() {
    $blocks = parent::getRemarkupBlocks();

    switch ($this->getTransactionType()) {
      case self::TYPE_BODY:
        $blocks[] = $this->getNewValue();
        break;
    }

    return $blocks;
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    switch ($this->getTransactionType()) {
      case self::TYPE_PHAME_TITLE:
      case self::TYPE_BODY:
        return ($old === null);
    }
    return parent::shouldHide();
  }

  public function getIcon() {
    $old = $this->getOldValue();
    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return 'fa-plus';
        } else {
          return 'fa-pencil';
        }
        break;
      case self::TYPE_PHAME_TITLE:
      case self::TYPE_BODY:
      case self::TYPE_VISIBILITY:
        return 'fa-pencil';
        break;
    }
    return parent::getIcon();
  }

  public function getMailTags() {
    $tags = parent::getMailTags();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $tags[] = self::MAILTAG_COMMENT;
        break;
      case self::TYPE_TITLE:
      case self::TYPE_PHAME_TITLE:
      case self::TYPE_BODY:
        $tags[] = self::MAILTAG_CONTENT;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }


  public function getTitle() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return pht(
            '%s authored this post.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s updated the post\'s name to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        }
        break;
      case self::TYPE_BODY:
        return pht(
          '%s updated the blog post.',
          $this->renderHandleLink($author_phid));
        break;
      case self::TYPE_VISIBILITY:
        if ($new == PhameConstants::VISIBILITY_DRAFT) {
          return pht(
            '%s marked this post as a draft.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
          '%s published this post.',
          $this->renderHandleLink($author_phid));
        }
        break;
      case self::TYPE_PHAME_TITLE:
        return pht(
          '%s updated the post\'s Phame title to "%s".',
          $this->renderHandleLink($author_phid),
          rtrim($new, '/'));
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
      case self::TYPE_TITLE:
        if ($old === null) {
          return pht(
            '%s authored %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s updated the name for %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
        break;
      case self::TYPE_BODY:
        return pht(
          '%s updated the blog post %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case self::TYPE_VISIBILITY:
        if ($new == PhameConstants::VISIBILITY_DRAFT) {
          return pht(
            '%s marked %s as a draft.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s published %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
        break;
      case self::TYPE_PHAME_TITLE:
        return pht(
          '%s updated the Phame title for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
    }

    return parent::getTitleForFeed();
  }

  public function getRemarkupBodyForFeed(PhabricatorFeedStory $story) {
    $text = null;
    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($this->getOldValue() === null) {
          $post = $story->getPrimaryObject();
          $text = $post->getBody();
        }
        break;
      case self::TYPE_VISIBILITY:
        if ($this->getNewValue() == PhameConstants::VISIBILITY_PUBLISHED) {
          $post = $story->getPrimaryObject();
          $text = $post->getBody();
        }
        break;
      case self::TYPE_BODY:
        $text = $this->getNewValue();
        break;
    }

    return $text;
  }

  public function getColor() {
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return PhabricatorTransactions::COLOR_GREEN;
        }
        break;
    }

    return parent::getColor();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_BODY:
        return ($this->getOldValue() !== null);
    }

    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    switch ($this->getTransactionType()) {
      case self::TYPE_BODY:
        $old = $this->getOldValue();
        $new = $this->getNewValue();

        return $this->renderTextCorpusChangeDetails(
          $viewer,
          $old,
          $new);
    }

    return parent::renderChangeDetails($viewer);
  }

}
