<?php

final class ManiphestTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_TITLE = 'title';
  const TYPE_STATUS = 'status';
  const TYPE_DESCRIPTION = 'description';
  const TYPE_OWNER  = 'reassign';
  const TYPE_PRIORITY = 'priority';
  const TYPE_EDGE = 'edge';
  const TYPE_SUBPRIORITY = 'subpriority';
  const TYPE_MERGED_INTO = 'mergedinto';
  const TYPE_MERGED_FROM = 'mergedfrom';
  const TYPE_UNBLOCK = 'unblock';
  const TYPE_PARENT = 'parent';
  const TYPE_COVER_IMAGE = 'cover-image';
  const TYPE_POINTS = 'points';

  // NOTE: this type is deprecated. Keep it around for legacy installs
  // so any transactions render correctly.
  const TYPE_ATTACH = 'attach';

  const MAILTAG_STATUS = 'maniphest-status';
  const MAILTAG_OWNER = 'maniphest-owner';
  const MAILTAG_PRIORITY = 'maniphest-priority';
  const MAILTAG_CC = 'maniphest-cc';
  const MAILTAG_PROJECTS = 'maniphest-projects';
  const MAILTAG_COMMENT = 'maniphest-comment';
  const MAILTAG_COLUMN = 'maniphest-column';
  const MAILTAG_UNBLOCK = 'maniphest-unblock';
  const MAILTAG_OTHER = 'maniphest-other';


  public function getApplicationName() {
    return 'maniphest';
  }

  public function getApplicationTransactionType() {
    return ManiphestTaskPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new ManiphestTransactionComment();
  }

  public function shouldGenerateOldValue() {
    switch ($this->getTransactionType()) {
      case self::TYPE_EDGE:
      case self::TYPE_UNBLOCK:
        return false;
    }

    return parent::shouldGenerateOldValue();
  }

  protected function newRemarkupChanges() {
    $changes = array();

    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        $changes[] = $this->newRemarkupChange()
          ->setOldValue($this->getOldValue())
          ->setNewValue($this->getNewValue());
        break;
    }

    return $changes;
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $new = $this->getNewValue();
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_OWNER:
        if ($new) {
          $phids[] = $new;
        }

        if ($old) {
          $phids[] = $old;
        }
        break;
      case self::TYPE_MERGED_INTO:
        $phids[] = $new;
        break;
      case self::TYPE_MERGED_FROM:
        $phids = array_merge($phids, $new);
        break;
      case self::TYPE_EDGE:
        $phids = array_mergev(
          array(
            $phids,
            array_keys(nonempty($old, array())),
            array_keys(nonempty($new, array())),
          ));
        break;
      case self::TYPE_ATTACH:
        $old = nonempty($old, array());
        $new = nonempty($new, array());
        $phids = array_mergev(
          array(
            $phids,
            array_keys(idx($new, 'FILE', array())),
            array_keys(idx($old, 'FILE', array())),
          ));
        break;
      case self::TYPE_UNBLOCK:
        foreach (array_keys($new) as $phid) {
          $phids[] = $phid;
        }
        break;
      case self::TYPE_STATUS:
        $commit_phid = $this->getMetadataValue('commitPHID');
        if ($commit_phid) {
          $phids[] = $commit_phid;
        }
        break;
    }

    return $phids;
  }

  public function shouldHide() {
    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_EDGE:
        $commit_phid = $this->getMetadataValue('commitPHID');
        $edge_type = $this->getMetadataValue('edge:type');

        if ($edge_type == ManiphestTaskHasCommitEdgeType::EDGECONST) {
          if ($commit_phid) {
            return true;
          }
        }
        break;
      case self::TYPE_DESCRIPTION:
      case self::TYPE_PRIORITY:
      case self::TYPE_STATUS:
        if ($this->getOldValue() === null) {
          return true;
        } else {
          return false;
        }
        break;
      case self::TYPE_SUBPRIORITY:
      case self::TYPE_PARENT:
        return true;
      case self::TYPE_COVER_IMAGE:
        // At least for now, don't show these.
        return true;
      case self::TYPE_POINTS:
        if (!ManiphestTaskPoints::getIsEnabled()) {
          return true;
        }
    }

    return parent::shouldHide();
  }

  public function shouldHideForMail(array $xactions) {
    switch ($this->getTransactionType()) {
      case self::TYPE_POINTS:
        return true;
    }

    return parent::shouldHideForMail($xactions);
  }

  public function shouldHideForFeed() {
    switch ($this->getTransactionType()) {
      case self::TYPE_UNBLOCK:
        // Hide "alice created X, a task blocking Y." from feed because it
        // will almost always appear adjacent to "alice created Y".
        $is_new = $this->getMetadataValue('blocker.new');
        if ($is_new) {
          return true;
        }
        break;
      case self::TYPE_POINTS:
        return true;
    }

    return parent::shouldHideForFeed();
  }

  public function getActionStrength() {
    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        return 1.4;
      case self::TYPE_STATUS:
        return 1.3;
      case self::TYPE_OWNER:
        return 1.2;
      case self::TYPE_PRIORITY:
        return 1.1;
    }

    return parent::getActionStrength();
  }


  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_OWNER:
        if ($this->getAuthorPHID() == $new) {
          return 'green';
        } else if (!$new) {
          return 'black';
        } else if (!$old) {
          return 'green';
        } else {
          return 'green';
        }

      case self::TYPE_STATUS:
        $color = ManiphestTaskStatus::getStatusColor($new);
        if ($color !== null) {
          return $color;
        }

        if (ManiphestTaskStatus::isOpenStatus($new)) {
          return 'green';
        } else {
          return 'indigo';
        }

      case self::TYPE_PRIORITY:
        if ($old == ManiphestTaskPriority::getDefaultPriority()) {
          return 'green';
        } else if ($old > $new) {
          return 'grey';
        } else {
          return 'yellow';
        }

      case self::TYPE_MERGED_FROM:
        return 'orange';

      case self::TYPE_MERGED_INTO:
        return 'indigo';
    }

    return parent::getColor();
  }

  public function getActionName() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return pht('Created');
        }

        return pht('Retitled');

      case self::TYPE_STATUS:
        $action = ManiphestTaskStatus::getStatusActionName($new);
        if ($action) {
          return $action;
        }

        $old_closed = ManiphestTaskStatus::isClosedStatus($old);
        $new_closed = ManiphestTaskStatus::isClosedStatus($new);

        if ($new_closed && !$old_closed) {
          return pht('Closed');
        } else if (!$new_closed && $old_closed) {
          return pht('Reopened');
        } else {
          return pht('Changed Status');
        }

      case self::TYPE_DESCRIPTION:
        return pht('Edited');

      case self::TYPE_OWNER:
        if ($this->getAuthorPHID() == $new) {
          return pht('Claimed');
        } else if (!$new) {
          return pht('Unassigned');
        } else if (!$old) {
          return pht('Assigned');
        } else {
          return pht('Reassigned');
        }

      case PhabricatorTransactions::TYPE_COLUMNS:
        return pht('Changed Project Column');

      case self::TYPE_PRIORITY:
        if ($old == ManiphestTaskPriority::getDefaultPriority()) {
          return pht('Triaged');
        } else if ($old > $new) {
          return pht('Lowered Priority');
        } else {
          return pht('Raised Priority');
        }

      case self::TYPE_EDGE:
      case self::TYPE_ATTACH:
        return pht('Attached');

      case self::TYPE_UNBLOCK:
        $old_status = head($old);
        $new_status = head($new);

        $old_closed = ManiphestTaskStatus::isClosedStatus($old_status);
        $new_closed = ManiphestTaskStatus::isClosedStatus($new_status);

        if ($old_closed && !$new_closed) {
          return pht('Block');
        } else if (!$old_closed && $new_closed) {
          return pht('Unblock');
        } else {
          return pht('Blocker');
        }

      case self::TYPE_MERGED_INTO:
      case self::TYPE_MERGED_FROM:
        return pht('Merged');

    }

    return parent::getActionName();
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_OWNER:
        return 'fa-user';

      case self::TYPE_TITLE:
        if ($old === null) {
          return 'fa-pencil';
        }

        return 'fa-pencil';

      case self::TYPE_STATUS:
        $action = ManiphestTaskStatus::getStatusIcon($new);
        if ($action !== null) {
          return $action;
        }

        if (ManiphestTaskStatus::isClosedStatus($new)) {
          return 'fa-check';
        } else {
          return 'fa-pencil';
        }

      case self::TYPE_DESCRIPTION:
        return 'fa-pencil';

      case PhabricatorTransactions::TYPE_COLUMNS:
        return 'fa-columns';

      case self::TYPE_MERGED_INTO:
        return 'fa-check';
      case self::TYPE_MERGED_FROM:
        return 'fa-compress';

      case self::TYPE_PRIORITY:
        if ($old == ManiphestTaskPriority::getDefaultPriority()) {
          return 'fa-arrow-right';
        } else if ($old > $new) {
          return 'fa-arrow-down';
        } else {
          return 'fa-arrow-up';
        }

      case self::TYPE_EDGE:
      case self::TYPE_ATTACH:
        return 'fa-thumb-tack';

      case self::TYPE_UNBLOCK:
        return 'fa-shield';

    }

    return parent::getIcon();
  }



  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_CREATE:
        return pht(
          '%s created this task.',
          $this->renderHandleLink($author_phid));
      case PhabricatorTransactions::TYPE_SUBTYPE:
        return pht(
          '%s changed the subtype of this task from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderSubtypeName($old),
          $this->renderSubtypeName($new));
      case self::TYPE_TITLE:
        if ($old === null) {
          return pht(
            '%s created this task.',
            $this->renderHandleLink($author_phid));
        }
        return pht(
          '%s changed the title from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);

      case self::TYPE_DESCRIPTION:
        return pht(
          '%s edited the task description.',
          $this->renderHandleLink($author_phid));

      case self::TYPE_STATUS:
        $old_closed = ManiphestTaskStatus::isClosedStatus($old);
        $new_closed = ManiphestTaskStatus::isClosedStatus($new);

        $old_name = ManiphestTaskStatus::getTaskStatusName($old);
        $new_name = ManiphestTaskStatus::getTaskStatusName($new);

        $commit_phid = $this->getMetadataValue('commitPHID');

        if ($new_closed && !$old_closed) {
          if ($new == ManiphestTaskStatus::getDuplicateStatus()) {
            if ($commit_phid) {
              return pht(
                '%s closed this task as a duplicate by committing %s.',
                $this->renderHandleLink($author_phid),
                $this->renderHandleLink($commit_phid));
            } else {
              return pht(
                '%s closed this task as a duplicate.',
                $this->renderHandleLink($author_phid));
            }
          } else {
            if ($commit_phid) {
              return pht(
                '%s closed this task as "%s" by committing %s.',
                $this->renderHandleLink($author_phid),
                $new_name,
                $this->renderHandleLink($commit_phid));
            } else {
              return pht(
                '%s closed this task as "%s".',
                $this->renderHandleLink($author_phid),
                $new_name);
            }
          }
        } else if (!$new_closed && $old_closed) {
          if ($commit_phid) {
            return pht(
              '%s reopened this task as "%s" by committing %s.',
              $this->renderHandleLink($author_phid),
              $new_name,
              $this->renderHandleLink($commit_phid));
          } else {
            return pht(
              '%s reopened this task as "%s".',
              $this->renderHandleLink($author_phid),
              $new_name);
          }
        } else {
          if ($commit_phid) {
            return pht(
              '%s changed the task status from "%s" to "%s" by committing %s.',
              $this->renderHandleLink($author_phid),
              $old_name,
              $new_name,
              $this->renderHandleLink($commit_phid));
          } else {
            return pht(
              '%s changed the task status from "%s" to "%s".',
              $this->renderHandleLink($author_phid),
              $old_name,
              $new_name);
          }
        }

      case self::TYPE_UNBLOCK:
        $blocker_phid = key($new);
        $old_status = head($old);
        $new_status = head($new);

        $old_closed = ManiphestTaskStatus::isClosedStatus($old_status);
        $new_closed = ManiphestTaskStatus::isClosedStatus($new_status);

        $old_name = ManiphestTaskStatus::getTaskStatusName($old_status);
        $new_name = ManiphestTaskStatus::getTaskStatusName($new_status);

        if ($this->getMetadataValue('blocker.new')) {
          return pht(
            '%s created subtask %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($blocker_phid));
        } else if ($old_closed && !$new_closed) {
          return pht(
            '%s reopened subtask %s as "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($blocker_phid),
            $new_name);
        } else if (!$old_closed && $new_closed) {
          return pht(
            '%s closed subtask %s as "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($blocker_phid),
            $new_name);
        } else {
          return pht(
            '%s changed the status of subtask %s from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($blocker_phid),
            $old_name,
            $new_name);
        }

      case self::TYPE_OWNER:
        if ($author_phid == $new) {
          return pht(
            '%s claimed this task.',
            $this->renderHandleLink($author_phid));
        } else if (!$new) {
          return pht(
            '%s removed %s as the assignee of this task.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old));
        } else if (!$old) {
          return pht(
            '%s assigned this task to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($new));
        } else {
          return pht(
            '%s reassigned this task from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }

      case self::TYPE_PRIORITY:
        $old_name = ManiphestTaskPriority::getTaskPriorityName($old);
        $new_name = ManiphestTaskPriority::getTaskPriorityName($new);

        if ($old == ManiphestTaskPriority::getDefaultPriority()) {
          return pht(
            '%s triaged this task as "%s" priority.',
            $this->renderHandleLink($author_phid),
            $new_name);
        } else if ($old > $new) {
          return pht(
            '%s lowered the priority of this task from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old_name,
            $new_name);
        } else {
          return pht(
            '%s raised the priority of this task from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old_name,
            $new_name);
        }

      case self::TYPE_ATTACH:
        $old = nonempty($old, array());
        $new = nonempty($new, array());
        $new = array_keys(idx($new, 'FILE', array()));
        $old = array_keys(idx($old, 'FILE', array()));

        $added = array_diff($new, $old);
        $removed = array_diff($old, $new);
        if ($added && !$removed) {
          return pht(
            '%s attached %s file(s): %s.',
            $this->renderHandleLink($author_phid),
            phutil_count($added),
            $this->renderHandleList($added));
        } else if ($removed && !$added) {
          return pht(
            '%s detached %s file(s): %s.',
            $this->renderHandleLink($author_phid),
            phutil_count($removed),
            $this->renderHandleList($removed));
        } else {
          return pht(
            '%s changed file(s), attached %s: %s; detached %s: %s.',
            $this->renderHandleLink($author_phid),
            phutil_count($added),
            $this->renderHandleList($added),
            phutil_count($removed),
            $this->renderHandleList($removed));
        }

      case self::TYPE_MERGED_INTO:
        return pht(
          '%s closed this task as a duplicate of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($new));
        break;

      case self::TYPE_MERGED_FROM:
        return pht(
          '%s merged %s task(s): %s.',
          $this->renderHandleLink($author_phid),
          phutil_count($new),
          $this->renderHandleList($new));
        break;

      case self::TYPE_POINTS:
        if ($old === null) {
          return pht(
            '%s set the point value for this task to %s.',
            $this->renderHandleLink($author_phid),
            $new);
        } else if ($new === null) {
          return pht(
            '%s removed the point value for this task.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s changed the point value for this task from %s to %s.',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }

    }

    return parent::getTitle();
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        if ($old === null) {
          return pht(
            '%s created %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }

        return pht(
          '%s renamed %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid),
          $old,
          $new);

      case self::TYPE_DESCRIPTION:
        return pht(
          '%s edited the description of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));

      case self::TYPE_STATUS:
        $old_closed = ManiphestTaskStatus::isClosedStatus($old);
        $new_closed = ManiphestTaskStatus::isClosedStatus($new);

        $old_name = ManiphestTaskStatus::getTaskStatusName($old);
        $new_name = ManiphestTaskStatus::getTaskStatusName($new);

        $commit_phid = $this->getMetadataValue('commitPHID');

        if ($new_closed && !$old_closed) {
          if ($new == ManiphestTaskStatus::getDuplicateStatus()) {
            if ($commit_phid) {
              return pht(
                '%s closed %s as a duplicate by committing %s.',
                $this->renderHandleLink($author_phid),
                $this->renderHandleLink($object_phid),
                $this->renderHandleLink($commit_phid));
            } else {
              return pht(
                '%s closed %s as a duplicate.',
                $this->renderHandleLink($author_phid),
                $this->renderHandleLink($object_phid));
            }
          } else {
            if ($commit_phid) {
              return pht(
                '%s closed %s as "%s" by committing %s.',
                $this->renderHandleLink($author_phid),
                $this->renderHandleLink($object_phid),
                $new_name,
                $this->renderHandleLink($commit_phid));
            } else {
              return pht(
                '%s closed %s as "%s".',
                $this->renderHandleLink($author_phid),
                $this->renderHandleLink($object_phid),
                $new_name);
            }
          }
        } else if (!$new_closed && $old_closed) {
          if ($commit_phid) {
            return pht(
              '%s reopened %s as "%s" by committing %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid),
              $new_name,
              $this->renderHandleLink($commit_phid));
          } else {
            return pht(
              '%s reopened %s as "%s".',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid),
              $new_name);
          }
        } else {
          if ($commit_phid) {
            return pht(
              '%s changed the status of %s from "%s" to "%s" by committing %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid),
              $old_name,
              $new_name,
              $this->renderHandleLink($commit_phid));
          } else {
            return pht(
              '%s changed the status of %s from "%s" to "%s".',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid),
              $old_name,
              $new_name);
          }
        }

      case self::TYPE_UNBLOCK:
        $blocker_phid = key($new);
        $old_status = head($old);
        $new_status = head($new);

        $old_closed = ManiphestTaskStatus::isClosedStatus($old_status);
        $new_closed = ManiphestTaskStatus::isClosedStatus($new_status);

        $old_name = ManiphestTaskStatus::getTaskStatusName($old_status);
        $new_name = ManiphestTaskStatus::getTaskStatusName($new_status);

        if ($old_closed && !$new_closed) {
          return pht(
            '%s reopened %s, a subtask of %s, as "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($blocker_phid),
            $this->renderHandleLink($object_phid),
            $new_name);
        } else if (!$old_closed && $new_closed) {
          return pht(
            '%s closed %s, a subtask of %s, as "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($blocker_phid),
            $this->renderHandleLink($object_phid),
            $new_name);
        } else {
          return pht(
            '%s changed the status of %s, a subtask of %s, '.
            'from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($blocker_phid),
            $this->renderHandleLink($object_phid),
            $old_name,
            $new_name);
        }

      case self::TYPE_OWNER:
        if ($author_phid == $new) {
          return pht(
            '%s claimed %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else if (!$new) {
          return pht(
            '%s placed %s up for grabs.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else if (!$old) {
          return pht(
            '%s assigned %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $this->renderHandleLink($new));
        } else {
          return pht(
            '%s reassigned %s from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }

      case self::TYPE_PRIORITY:
        $old_name = ManiphestTaskPriority::getTaskPriorityName($old);
        $new_name = ManiphestTaskPriority::getTaskPriorityName($new);

        if ($old == ManiphestTaskPriority::getDefaultPriority()) {
          return pht(
            '%s triaged %s as "%s" priority.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $new_name);
        } else if ($old > $new) {
          return pht(
            '%s lowered the priority of %s from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $old_name,
            $new_name);
        } else {
          return pht(
            '%s raised the priority of %s from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $old_name,
            $new_name);
        }

      case self::TYPE_ATTACH:
        $old = nonempty($old, array());
        $new = nonempty($new, array());
        $new = array_keys(idx($new, 'FILE', array()));
        $old = array_keys(idx($old, 'FILE', array()));

        $added = array_diff($new, $old);
        $removed = array_diff($old, $new);
        if ($added && !$removed) {
          return pht(
            '%s attached %d file(s) of %s: %s',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            count($added),
            $this->renderHandleList($added));
        } else if ($removed && !$added) {
          return pht(
            '%s detached %d file(s) of %s: %s',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            count($removed),
            $this->renderHandleList($removed));
        } else {
          return pht(
            '%s changed file(s) for %s, attached %d: %s; detached %d: %s',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            count($added),
            $this->renderHandleList($added),
            count($removed),
            $this->renderHandleList($removed));
        }

      case self::TYPE_MERGED_INTO:
        return pht(
          '%s merged task %s into %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid),
          $this->renderHandleLink($new));

      case self::TYPE_MERGED_FROM:
        return pht(
          '%s merged %s task(s) %s into %s.',
          $this->renderHandleLink($author_phid),
          phutil_count($new),
          $this->renderHandleList($new),
          $this->renderHandleLink($object_phid));

      case PhabricatorTransactions::TYPE_SUBTYPE:
        return pht(
          '%s changed the subtype of %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid),
          $this->renderSubtypeName($old),
          $this->renderSubtypeName($new));
    }

    return parent::getTitleForFeed();
  }

  private function renderSubtypeName($value) {
    $object = $this->getObject();
    $map = $object->newEditEngineSubtypeMap();
    if (!isset($map[$value])) {
      return $value;
    }

    return $map[$value]->getName();
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
    $tags = array();
    switch ($this->getTransactionType()) {
      case self::TYPE_MERGED_INTO:
      case self::TYPE_STATUS:
        $tags[] = self::MAILTAG_STATUS;
        break;
      case self::TYPE_OWNER:
        $tags[] = self::MAILTAG_OWNER;
        break;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $tags[] = self::MAILTAG_CC;
        break;
      case PhabricatorTransactions::TYPE_EDGE:
        switch ($this->getMetadataValue('edge:type')) {
          case PhabricatorProjectObjectHasProjectEdgeType::EDGECONST:
            $tags[] = self::MAILTAG_PROJECTS;
            break;
          default:
            $tags[] = self::MAILTAG_OTHER;
            break;
        }
        break;
      case self::TYPE_PRIORITY:
        $tags[] = self::MAILTAG_PRIORITY;
        break;
      case self::TYPE_UNBLOCK:
        $tags[] = self::MAILTAG_UNBLOCK;
        break;
      case PhabricatorTransactions::TYPE_COLUMNS:
        $tags[] = self::MAILTAG_COLUMN;
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

  public function getNoEffectDescription() {

    switch ($this->getTransactionType()) {
      case self::TYPE_STATUS:
        return pht('The task already has the selected status.');
      case self::TYPE_OWNER:
        return pht('The task already has the selected owner.');
      case self::TYPE_PRIORITY:
        return pht('The task already has the selected priority.');
    }

    return parent::getNoEffectDescription();
  }

}
