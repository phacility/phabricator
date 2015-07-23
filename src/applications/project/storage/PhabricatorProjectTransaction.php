<?php

final class PhabricatorProjectTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME       = 'project:name';
  const TYPE_SLUGS      = 'project:slugs';
  const TYPE_STATUS     = 'project:status';
  const TYPE_IMAGE      = 'project:image';
  const TYPE_ICON       = 'project:icon';
  const TYPE_COLOR      = 'project:color';
  const TYPE_LOCKED     = 'project:locked';

  // NOTE: This is deprecated, members are just a normal edge now.
  const TYPE_MEMBERS    = 'project:members';

  const MAILTAG_METADATA    = 'project-metadata';
  const MAILTAG_MEMBERS     = 'project-members';
  const MAILTAG_SUBSCRIBERS = 'project-subscribers';
  const MAILTAG_WATCHERS    = 'project-watchers';
  const MAILTAG_OTHER       = 'project-other';

  public function getApplicationName() {
    return 'project';
  }

  public function getApplicationTransactionType() {
    return PhabricatorProjectProjectPHIDType::TYPECONST;
  }

  public function getRequiredHandlePHIDs() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $req_phids = array();
    switch ($this->getTransactionType()) {
      case self::TYPE_MEMBERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);
        $req_phids = array_merge($add, $rem);
        break;
      case self::TYPE_IMAGE:
        $req_phids[] = $old;
        $req_phids[] = $new;
        break;
    }

    return array_merge($req_phids, parent::getRequiredHandlePHIDs());
  }

  public function getColor() {

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_STATUS:
        if ($old == 0) {
          return 'red';
        } else {
          return 'green';
        }
      }
    return parent::getColor();
  }

  public function getIcon() {

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_STATUS:
        if ($old == 0) {
          return 'fa-ban';
        } else {
          return 'fa-check';
        }
      case self::TYPE_LOCKED:
        if ($new) {
          return 'fa-lock';
        } else {
          return 'fa-unlock';
        }
      case self::TYPE_ICON:
        return $new;
      case self::TYPE_IMAGE:
        return 'fa-photo';
      case self::TYPE_MEMBERS:
        return 'fa-user';
      case self::TYPE_SLUGS:
        return 'fa-tag';
    }
    return parent::getIcon();
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $author_handle = $this->renderHandleLink($this->getAuthorPHID());

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this project.',
            $author_handle);
        } else {
          return pht(
            '%s renamed this project from "%s" to "%s".',
            $author_handle,
            $old,
            $new);
        }
        break;

      case self::TYPE_STATUS:
        if ($old == 0) {
          return pht(
            '%s archived this project.',
            $author_handle);
        } else {
          return pht(
            '%s activated this project.',
            $author_handle);
        }
        break;

      case self::TYPE_IMAGE:
        // TODO: Some day, it would be nice to show the images.
        if (!$old) {
          return pht(
            "%s set this project's image to %s.",
            $author_handle,
            $this->renderHandleLink($new));
        } else if (!$new) {
          return pht(
            "%s removed this project's image.",
            $author_handle);
        } else {
          return pht(
            "%s updated this project's image from %s to %s.",
            $author_handle,
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }
        break;

      case self::TYPE_ICON:
        return pht(
          "%s set this project's icon to %s.",
          $author_handle,
          PhabricatorProjectIcon::getLabel($new));
        break;

      case self::TYPE_COLOR:
        return pht(
          "%s set this project's color to %s.",
          $author_handle,
          PHUITagView::getShadeName($new));
        break;

      case self::TYPE_LOCKED:
        if ($new) {
          return pht(
            "%s locked this project's membership.",
            $author_handle);
        } else {
          return pht(
            "%s unlocked this project's membership.",
            $author_handle);
        }
        break;

      case self::TYPE_SLUGS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ($add && $rem) {
          return pht(
            '%s changed project hashtag(s), added %d: %s; removed %d: %s.',
            $author_handle,
            count($add),
            $this->renderSlugList($add),
            count($rem),
            $this->renderSlugList($rem));
        } else if ($add) {
          return pht(
            '%s added %d project hashtag(s): %s.',
            $author_handle,
            count($add),
            $this->renderSlugList($add));
        } else if ($rem) {
            return pht(
              '%s removed %d project hashtag(s): %s.',
              $author_handle,
              count($rem),
              $this->renderSlugList($rem));
        }
        break;

      case self::TYPE_MEMBERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ($add && $rem) {
          return pht(
            '%s changed project member(s), added %d: %s; removed %d: %s.',
            $author_handle,
            count($add),
            $this->renderHandleList($add),
            count($rem),
            $this->renderHandleList($rem));
        } else if ($add) {
          if (count($add) == 1 && (head($add) == $this->getAuthorPHID())) {
            return pht(
              '%s joined this project.',
              $author_handle);
          } else {
            return pht(
              '%s added %d project member(s): %s.',
              $author_handle,
              count($add),
              $this->renderHandleList($add));
          }
        } else if ($rem) {
          if (count($rem) == 1 && (head($rem) == $this->getAuthorPHID())) {
            return pht(
              '%s left this project.',
              $author_handle);
          } else {
            return pht(
              '%s removed %d project member(s): %s.',
              $author_handle,
              count($rem),
              $this->renderHandleList($rem));
          }
        }
        break;
    }

    return parent::getTitle();
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();
    $author_handle = $this->renderHandleLink($author_phid);
    $object_handle = $this->renderHandleLink($object_phid);

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created %s.',
            $author_handle,
            $object_handle);
        } else {
          return pht(
            '%s renamed %s from "%s" to "%s".',
            $author_handle,
            $object_handle,
            $old,
            $new);
        }
      case self::TYPE_STATUS:
        if ($old == 0) {
          return pht(
            '%s archived %s.',
            $author_handle,
            $object_handle);
        } else {
          return pht(
            '%s activated %s.',
            $author_handle,
            $object_handle);
        }
      case self::TYPE_IMAGE:
        // TODO: Some day, it would be nice to show the images.
        if (!$old) {
          return pht(
            '%s set the image for %s to %s.',
            $author_handle,
            $object_handle,
            $this->renderHandleLink($new));
        } else if (!$new) {
          return pht(
            '%s removed the image for %s.',
            $author_handle,
            $object_handle);
        } else {
          return pht(
            '%s updated the image for %s from %s to %s.',
            $author_handle,
            $object_handle,
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }

      case self::TYPE_ICON:
        return pht(
          '%s set the icon for %s to %s.',
          $author_handle,
          $object_handle,
          PhabricatorProjectIcon::getLabel($new));

      case self::TYPE_COLOR:
        return pht(
          '%s set the color for %s to %s.',
          $author_handle,
          $object_handle,
          PHUITagView::getShadeName($new));

      case self::TYPE_LOCKED:
        if ($new) {
          return pht(
            '%s locked %s membership.',
            $author_handle,
            $object_handle);
        } else {
          return pht(
            '%s unlocked %s membership.',
            $author_handle,
            $object_handle);
        }

      case self::TYPE_SLUGS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ($add && $rem) {
          return pht(
            '%s changed %s hashtag(s), added %d: %s; removed %d: %s.',
            $author_handle,
            $object_handle,
            count($add),
            $this->renderSlugList($add),
            count($rem),
            $this->renderSlugList($rem));
        } else if ($add) {
          return pht(
            '%s added %d %s hashtag(s): %s.',
            $author_handle,
            count($add),
            $object_handle,
            $this->renderSlugList($add));
        } else if ($rem) {
          return pht(
            '%s removed %d %s hashtag(s): %s.',
            $author_handle,
            count($rem),
            $object_handle,
            $this->renderSlugList($rem));
        }
    }

    return parent::getTitleForFeed();
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
      case self::TYPE_SLUGS:
      case self::TYPE_IMAGE:
      case self::TYPE_ICON:
      case self::TYPE_COLOR:
        $tags[] = self::MAILTAG_METADATA;
        break;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $tags[] = self::MAILTAG_SUBSCRIBERS;
        break;
      case PhabricatorTransactions::TYPE_EDGE:
        $type = $this->getMetadata('edge:type');
        $type = head($type);
        $type_member = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;
        $type_watcher = PhabricatorObjectHasWatcherEdgeType::EDGECONST;
        if ($type == $type_member) {
          $tags[] = self::MAILTAG_MEMBERS;
        } else if ($type == $type_watcher) {
          $tags[] = self::MAILTAG_WATCHERS;
        } else {
          $tags[] = self::MAILTAG_OTHER;
        }
        break;
      case self::TYPE_STATUS:
      case self::TYPE_LOCKED:
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

  private function renderSlugList($slugs) {
    return implode(', ', $slugs);
  }

}
