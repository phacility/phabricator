<?php

final class PholioTransaction extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'pholio';
  }

  public function getApplicationTransactionType() {
    return PholioPHIDTypeMock::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PholioTransactionComment();
  }

  public function getApplicationTransactionViewObject() {
    return new PholioTransactionView();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();
    $phids[] = $this->getObjectPHID();

    $new = $this->getNewValue();
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case PholioTransactionType::TYPE_IMAGE_FILE:
        $phids = array_merge($phids, $new, $old);
        break;
      case PholioTransactionType::TYPE_IMAGE_REPLACE:
        $phids[] = $new;
        $phids[] = $old;
        break;
      case PholioTransactionType::TYPE_IMAGE_DESCRIPTION:
      case PholioTransactionType::TYPE_IMAGE_NAME:
      case PholioTransactionType::TYPE_IMAGE_SEQUENCE:
        $phids[] = key($new);
        break;
    }

    return $phids;
  }

  public function shouldHide() {
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case PholioTransactionType::TYPE_DESCRIPTION:
        return ($old === null);
      case PholioTransactionType::TYPE_IMAGE_NAME:
      case PholioTransactionType::TYPE_IMAGE_DESCRIPTION:
        return ($old === array(null => null));
      // this is boring / silly to surface; changing sequence is NBD
      case PholioTransactionType::TYPE_IMAGE_SEQUENCE:
        return true;
    }

    return parent::shouldHide();
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case PholioTransactionType::TYPE_INLINE:
        return 'fa-comment';
      case PholioTransactionType::TYPE_NAME:
      case PholioTransactionType::TYPE_DESCRIPTION:
      case PholioTransactionType::TYPE_STATUS:
      case PholioTransactionType::TYPE_IMAGE_NAME:
      case PholioTransactionType::TYPE_IMAGE_DESCRIPTION:
      case PholioTransactionType::TYPE_IMAGE_SEQUENCE:
        return 'fa-pencil';
      case PholioTransactionType::TYPE_IMAGE_FILE:
      case PholioTransactionType::TYPE_IMAGE_REPLACE:
        return 'fa-picture-o';
    }

    return parent::getIcon();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case PholioTransactionType::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else {
          return pht(
            '%s renamed this mock from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case PholioTransactionType::TYPE_DESCRIPTION:
        return pht(
          "%s updated the mock's description.",
          $this->renderHandleLink($author_phid));
        break;
      case PholioTransactionType::TYPE_STATUS:
        return pht(
          "%s updated the mock's status.",
          $this->renderHandleLink($author_phid));
        break;
      case PholioTransactionType::TYPE_INLINE:
        $count = 1;
        foreach ($this->getTransactionGroup() as $xaction) {
          if ($xaction->getTransactionType() == $type) {
            $count++;
          }
        }

        return pht(
          '%s added %d inline comment(s).',
          $this->renderHandleLink($author_phid),
          $count);
        break;
      case PholioTransactionType::TYPE_IMAGE_REPLACE:
        return pht(
          '%s replaced %s with %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($old),
          $this->renderHandleLink($new));
        break;
      case PholioTransactionType::TYPE_IMAGE_FILE:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ($add && $rem) {
          return pht(
            '%s edited image(s), added %d: %s; removed %d: %s.',
            $this->renderHandleLink($author_phid),
            count($add),
            $this->renderHandleList($add),
            count($rem),
            $this->renderHandleList($rem));
        } else if ($add) {
          return pht(
            '%s added %d image(s): %s.',
            $this->renderHandleLink($author_phid),
            count($add),
            $this->renderHandleList($add));
        } else {
          return pht(
            '%s removed %d image(s): %s.',
            $this->renderHandleLink($author_phid),
            count($rem),
            $this->renderHandleList($rem));
        }
        break;

      case PholioTransactionType::TYPE_IMAGE_NAME:
        return pht(
          '%s renamed an image (%s) from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink(key($new)),
          reset($old),
          reset($new));
        break;
      case PholioTransactionType::TYPE_IMAGE_DESCRIPTION:
        return pht(
          '%s updated an image\'s (%s) description.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink(key($new)));
        break;
      case PholioTransactionType::TYPE_IMAGE_SEQUENCE:
        return pht(
          '%s updated an image\'s (%s) sequence.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink(key($new)));
        break;
    }

    return parent::getTitle();
  }

  public function getTitleForFeed(PhabricatorFeedStory $story) {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case PholioTransactionType::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s renamed %s from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $old,
            $new);
        }
        break;
      case PholioTransactionType::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case PholioTransactionType::TYPE_STATUS:
        return pht(
          '%s updated the status for %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case PholioTransactionType::TYPE_INLINE:
        return pht(
          '%s added an inline comment to %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case PholioTransactionType::TYPE_IMAGE_REPLACE:
      case PholioTransactionType::TYPE_IMAGE_FILE:
        return pht(
          '%s updated images of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case PholioTransactionType::TYPE_IMAGE_NAME:
        return pht(
          '%s updated the image names of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case PholioTransactionType::TYPE_IMAGE_DESCRIPTION:
        return pht(
          '%s updated image descriptions of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
      case PholioTransactionType::TYPE_IMAGE_SEQUENCE:
        return pht(
          '%s updated image sequence of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));
        break;
    }

    return parent::getTitleForFeed($story);
  }

  public function getBodyForFeed(PhabricatorFeedStory $story) {
    $text = null;
    switch ($this->getTransactionType()) {
      case PholioTransactionType::TYPE_NAME:
        if ($this->getOldValue() === null) {
          $mock = $story->getPrimaryObject();
          $text = $mock->getDescription();
        }
        break;
      case PholioTransactionType::TYPE_INLINE:
        $text = $this->getComment()->getContent();
        break;
    }

    if ($text) {
      return phutil_escape_html_newlines(
        phutil_utf8_shorten($text, 128));
    }

    return parent::getBodyForFeed($story);
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case PholioTransactionType::TYPE_DESCRIPTION:
      case PholioTransactionType::TYPE_IMAGE_DESCRIPTION:
        return true;
    }
    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    if ($this->getTransactionType() ==
        PholioTransactionType::TYPE_IMAGE_DESCRIPTION) {
      $old = reset($old);
      $new = reset($new);
    }

    return $this->renderTextCorpusChangeDetails(
      $viewer,
      $old,
      $new);
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PholioTransactionType::TYPE_NAME:
        if ($old === null) {
          return PhabricatorTransactions::COLOR_GREEN;
        }
      case PholioTransactionType::TYPE_DESCRIPTION:
      case PholioTransactionType::TYPE_STATUS:
      case PholioTransactionType::TYPE_IMAGE_NAME:
      case PholioTransactionType::TYPE_IMAGE_DESCRIPTION:
      case PholioTransactionType::TYPE_IMAGE_SEQUENCE:
        return PhabricatorTransactions::COLOR_BLUE;
      case PholioTransactionType::TYPE_IMAGE_REPLACE:
        return PhabricatorTransactions::COLOR_YELLOW;
      case PholioTransactionType::TYPE_IMAGE_FILE:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);
        if ($add && $rem) {
          return PhabricatorTransactions::COLOR_YELLOW;
        } else if ($add) {
          return PhabricatorTransactions::COLOR_GREEN;
        } else {
          return PhabricatorTransactions::COLOR_RED;
        }
    }

    return parent::getColor();
  }

  public function getNoEffectDescription() {
    switch ($this->getTransactionType()) {
      case PholioTransactionType::TYPE_IMAGE_NAME:
        return pht('The image title was not updated.');
      case PholioTransactionType::TYPE_IMAGE_DESCRIPTION:
        return pht('The image description was not updated.');
      case PholioTransactionType::TYPE_IMAGE_SEQUENCE:
        return pht('The image sequence was not updated.');
    }

    return parent::getNoEffectDescription();
  }
}
