<?php

/**
 * @group paste
 */
final class PhabricatorPasteTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_CREATE = 'paste.create';
  const TYPE_TITLE = 'paste.title';
  const TYPE_LANGUAGE = 'paste.language';

  public function getApplicationName() {
    return 'pastebin';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPastePHIDTypePaste::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorPasteTransactionComment();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    switch ($this->getTransactionType()) {
      case self::TYPE_CREATE:
        $phids[] = $this->getObjectPHID();
        break;
    }

    return $phids;
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
      case self::TYPE_LANGUAGE:
        return $old === null;
    }
    return parent::shouldHide();
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case self::TYPE_CREATE:
        return 'create';
        break;
      case self::TYPE_TITLE:
      case self::TYPE_LANGUAGE:
        return 'edit';
        break;
    }
    return parent::getIcon();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case PhabricatorPasteTransaction::TYPE_CREATE:
        return pht(
          '%s created "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case PhabricatorPasteTransaction::TYPE_TITLE:
        return pht(
          '%s updated the paste\'s title to "%s".',
          $this->renderHandleLink($author_phid),
          $new);
        break;
      case PhabricatorPasteTransaction::TYPE_LANGUAGE:
        return pht(
          "%s updated the paste's language.",
          $this->renderHandleLink($author_phid));
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
      case PhabricatorPasteTransaction::TYPE_CREATE:
        return pht(
          '%s created %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case PhabricatorPasteTransaction::TYPE_TITLE:
        return pht(
          '%s updated the title for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case PhabricatorPasteTransaction::TYPE_LANGUAGE:
        return pht(
          '%s update the language for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
    }

    return parent::getTitleForFeed();
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorPasteTransaction::TYPE_CREATE:
        return PhabricatorTransactions::COLOR_GREEN;
    }

    return parent::getColor();
  }
}
