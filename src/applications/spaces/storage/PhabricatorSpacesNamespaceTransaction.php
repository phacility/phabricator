<?php

final class PhabricatorSpacesNamespaceTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'spaces:name';
  const TYPE_DEFAULT = 'spaces:default';
  const TYPE_DESCRIPTION = 'spaces:description';
  const TYPE_ARCHIVE = 'spaces:archive';

  public function getApplicationName() {
    return 'spaces';
  }

  public function getApplicationTransactionType() {
    return PhabricatorSpacesNamespacePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function shouldHide() {
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return ($old === null);
    }

    return parent::shouldHide();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return true;
    }

    return parent::hasChangeDetails();
  }

  public function getRemarkupBlocks() {
    $blocks = parent::getRemarkupBlocks();

    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        $blocks[] = $this->getNewValue();
        break;
    }

    return $blocks;
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $author_phid = $this->getAuthorPHID();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this space.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s renamed this space from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description for this space.',
            $this->renderHandleLink($author_phid));
      case self::TYPE_DEFAULT:
        return pht(
          '%s made this the default space.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_ARCHIVE:
        if ($new) {
          return pht(
            '%s archived this space.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s activated this space.',
            $this->renderHandleLink($author_phid));
        }
    }

    return parent::getTitle();
  }

}
