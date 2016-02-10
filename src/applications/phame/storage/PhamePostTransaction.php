<?php

final class PhamePostTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_TITLE            = 'phame.post.title';
  const TYPE_BODY             = 'phame.post.body';
  const TYPE_VISIBILITY       = 'phame.post.visibility';
  const TYPE_BLOG             = 'phame.post.blog';

  const MAILTAG_CONTENT       = 'phame-post-content';
  const MAILTAG_SUBSCRIBERS   = 'phame-post-subscribers';
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
    return parent::shouldHide();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    switch ($this->getTransactionType()) {
      case self::TYPE_BLOG:
        $old = $this->getOldValue();
        $new = $this->getNewValue();

        if ($old) {
          $phids[] = $old;
        }

        if ($new) {
          $phids[] = $new;
        }
        break;
    }

    return $phids;
  }


  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_CREATE:
        return 'fa-plus';
      break;
      case self::TYPE_VISIBILITY:
        if ($new == PhameConstants::VISIBILITY_PUBLISHED) {
          return 'fa-globe';
        } else {
          return 'fa-eye-slash';
        }
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
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $tags[] = self::MAILTAG_SUBSCRIBERS;
        break;
      case self::TYPE_TITLE:
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
      case PhabricatorTransactions::TYPE_CREATE:
        return pht(
          '%s authored this post.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_BLOG:
        return pht(
          '%s moved this post from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($old),
          $this->renderHandleLink($new));
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
      case PhabricatorTransactions::TYPE_CREATE:
        return pht(
          '%s authored %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
      case self::TYPE_BLOG:
        return pht(
          '%s moved post "%s" from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid),
          $this->renderHandleLink($old),
          $this->renderHandleLink($new));
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
    }

    return parent::getTitleForFeed();
  }

  public function getRemarkupBodyForFeed(PhabricatorFeedStory $story) {
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_BODY:
        if ($old === null) {
          return $this->getNewValue();
        }
      break;
    }

    return null;
  }

  public function getColor() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_CREATE:
        return PhabricatorTransactions::COLOR_GREEN;
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
