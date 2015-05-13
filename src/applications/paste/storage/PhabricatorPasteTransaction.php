<?php

final class PhabricatorPasteTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_CONTENT = 'paste.create';
  const TYPE_TITLE = 'paste.title';
  const TYPE_LANGUAGE = 'paste.language';

  const MAILTAG_CONTENT = 'paste-content';
  const MAILTAG_OTHER = 'paste-other';
  const MAILTAG_COMMENT = 'paste-comment';

  public function getApplicationName() {
    return 'pastebin';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPastePastePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorPasteTransactionComment();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
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
        return ($old === null);
    }
    return parent::shouldHide();
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        return 'fa-plus';
        break;
      case self::TYPE_TITLE:
      case self::TYPE_LANGUAGE:
        return 'fa-pencil';
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
      case self::TYPE_CONTENT:
        if ($old === null) {
          return pht(
            '%s created this paste.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s edited the content of this paste.',
            $this->renderHandleLink($author_phid));
        }
        break;
      case self::TYPE_TITLE:
        return pht(
          '%s updated the paste\'s title to "%s".',
          $this->renderHandleLink($author_phid),
          $new);
        break;
      case self::TYPE_LANGUAGE:
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
      case self::TYPE_CONTENT:
        if ($old === null) {
          return pht(
            '%s created %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s edited %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
        break;
      case self::TYPE_TITLE:
        return pht(
          '%s updated the title for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case self::TYPE_LANGUAGE:
        return pht(
          '%s updated the language for %s.',
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
      case self::TYPE_CONTENT:
        return PhabricatorTransactions::COLOR_GREEN;
    }

    return parent::getColor();
  }


  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        return ($this->getOldValue() !== null);
    }

    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    switch ($this->getTransactionType()) {
      case self::TYPE_CONTENT:
        $old = $this->getOldValue();
        $new = $this->getNewValue();

        $files = id(new PhabricatorFileQuery())
          ->setViewer($viewer)
          ->withPHIDs(array_filter(array($old, $new)))
          ->execute();
        $files = mpull($files, null, 'getPHID');

        $old_text = '';
        if (idx($files, $old)) {
          $old_text = $files[$old]->loadFileData();
        }

        $new_text = '';
        if (idx($files, $new)) {
          $new_text = $files[$new]->loadFileData();
        }

        return $this->renderTextCorpusChangeDetails(
          $viewer,
          $old_text,
          $new_text);
    }

    return parent::renderChangeDetails($viewer);
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
      case self::TYPE_CONTENT:
      case self::TYPE_LANGUAGE:
        $tags[] = self::MAILTAG_CONTENT;
        break;
      case PhabricatorTransactions::TYPE_COMMENT:
        $tags[] = self::MAILTAG_COMMENT;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

}
