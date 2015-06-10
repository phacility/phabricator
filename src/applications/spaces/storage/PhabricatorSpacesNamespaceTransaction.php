<?php

final class PhabricatorSpacesNamespaceTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'spaces:name';
  const TYPE_DEFAULT = 'spaces:default';

  public function getApplicationName() {
    return 'spaces';
  }

  public function getApplicationTransactionType() {
    return PhabricatorSpacesNamespacePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
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
      case self::TYPE_DEFAULT:
        return pht(
          '%s made this the default space.',
          $this->renderHandleLink($author_phid));
    }

    return parent::getTitle();
  }

}
