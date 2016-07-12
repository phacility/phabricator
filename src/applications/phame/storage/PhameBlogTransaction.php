<?php

final class PhameBlogTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME           = 'phame.blog.name';
  const TYPE_SUBTITLE       = 'phame.blog.subtitle';
  const TYPE_DESCRIPTION    = 'phame.blog.description';
  const TYPE_FULLDOMAIN     = 'phame.blog.full.domain';
  const TYPE_STATUS         = 'phame.blog.status';
  const TYPE_PARENTSITE     = 'phame.blog.parent.site';
  const TYPE_PARENTDOMAIN   = 'phame.blog.parent.domain';
  const TYPE_PROFILEIMAGE   = 'phame.blog.header.image';
  const TYPE_HEADERIMAGE    = 'phame.blog.profile.image';

  const MAILTAG_DETAILS       = 'phame-blog-details';
  const MAILTAG_SUBSCRIBERS   = 'phame-blog-subscribers';
  const MAILTAG_OTHER         = 'phame-blog-other';

  public function getApplicationName() {
    return 'phame';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPhameBlogPHIDType::TYPECONST;
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        if ($old === null) {
          return true;
        }
    }
    return parent::shouldHide();
  }

  public function getRequiredHandlePHIDs() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $req_phids = array();
    switch ($this->getTransactionType()) {
      case self::TYPE_PROFILEIMAGE:
      case self::TYPE_HEADERIMAGE:
        $req_phids[] = $old;
        $req_phids[] = $new;
        break;
    }

    return array_merge($req_phids, parent::getRequiredHandlePHIDs());
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return 'fa-plus';
        } else {
          return 'fa-pencil';
        }
        break;
      case self::TYPE_DESCRIPTION:
      case self::TYPE_FULLDOMAIN:
        return 'fa-pencil';
      case self::TYPE_HEADERIMAGE:
        return 'fa-image';
      case self::TYPE_PROFILEIMAGE:
        return 'fa-star';
      case self::TYPE_STATUS:
        if ($new == PhameBlog::STATUS_ARCHIVED) {
          return 'fa-ban';
        } else {
          return 'fa-check';
        }
        break;
    }
    return parent::getIcon();
  }

    public function getColor() {

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_STATUS:
        if ($new == PhameBlog::STATUS_ARCHIVED) {
          return 'violet';
        } else {
          return 'green';
        }
      }
    return parent::getColor();
  }

  public function getMailTags() {
    $tags = parent::getMailTags();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $tags[] = self::MAILTAG_SUBSCRIBERS;
        break;
      case self::TYPE_NAME:
      case self::TYPE_SUBTITLE:
      case self::TYPE_DESCRIPTION:
      case self::TYPE_FULLDOMAIN:
      case self::TYPE_PARENTSITE:
      case self::TYPE_PARENTDOMAIN:
      case self::TYPE_PROFILEIMAGE:
      case self::TYPE_HEADERIMAGE:
        $tags[] = self::MAILTAG_DETAILS;
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
          '%s created this blog.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this blog.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s updated the blog\'s name to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        }
        break;
      case self::TYPE_SUBTITLE:
        if ($old === null) {
          return pht(
            '%s set this blog\'s subtitle to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else {
          return pht(
            '%s updated the blog\'s subtitle to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        }
        break;
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the blog\'s description.',
          $this->renderHandleLink($author_phid));
        break;
      case self::TYPE_FULLDOMAIN:
        return pht(
          '%s updated the blog\'s full domain to "%s".',
          $this->renderHandleLink($author_phid),
          $new);
        break;
      case self::TYPE_PARENTSITE:
        if ($old === null) {
          return pht(
            '%s set this blog\'s parent site to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else {
          return pht(
            '%s updated the blog\'s parent site to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        }
        break;
      case self::TYPE_PARENTDOMAIN:
        if ($old === null) {
          return pht(
            '%s set this blog\'s parent domain to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else {
          return pht(
            '%s updated the blog\'s parent domain to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        }
        break;
      case self::TYPE_HEADERIMAGE:
        if (!$old) {
          return pht(
            "%s set this blog's header image to %s.",
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($new));
        } else if (!$new) {
          return pht(
            "%s removed this blog's header image.",
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            "%s updated this blog's header image from %s to %s.",
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }
        break;
      case self::TYPE_PROFILEIMAGE:
        if (!$old) {
          return pht(
            "%s set this blog's profile image to %s.",
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($new));
        } else if (!$new) {
          return pht(
            "%s removed this blog's profile image.",
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            "%s updated this blog's profile image from %s to %s.",
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }
        break;
      case self::TYPE_STATUS:
        switch ($new) {
          case PhameBlog::STATUS_ACTIVE:
            return pht(
              '%s published this blog.',
              $this->renderHandleLink($author_phid));
          case PhameBlog::STATUS_ARCHIVED:
            return pht(
              '%s archived this blog.',
              $this->renderHandleLink($author_phid));
        }

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
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s updated the name for %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
        break;
      case self::TYPE_SUBTITLE:
        if ($old === null) {
          return pht(
            '%s set the subtitle for %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s updated the subtitle for %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
        break;
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case self::TYPE_FULLDOMAIN:
        return pht(
          '%s updated the full domain for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case self::TYPE_PARENTSITE:
        return pht(
          '%s updated the parent site for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case self::TYPE_PARENTDOMAIN:
        return pht(
          '%s updated the parent domain for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case self::TYPE_HEADERIMAGE:
        return pht(
          '%s updated the header image for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case self::TYPE_PROFILEIMAGE:
        return pht(
          '%s updated the profile image for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case self::TYPE_STATUS:
        switch ($new) {
          case PhameBlog::STATUS_ACTIVE:
            return pht(
              '%s published the blog %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
          case PhameBlog::STATUS_ARCHIVED:
            return pht(
              '%s archived the blog %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
        }
        break;

    }

    return parent::getTitleForFeed();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return ($this->getOldValue() !== null);
    }

    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
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
