<?php

final class ManiphestTaskUnblockTransaction
  extends ManiphestTaskTransactionType {

  const TRANSACTIONTYPE = 'unblock';

  public function generateOldValue($object) {
    return null;
  }

  public function getActionName() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

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
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

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
        $this->renderAuthor(),
        $this->renderHandle($blocker_phid));
    } else if ($old_closed && !$new_closed) {
      return pht(
        '%s reopened subtask %s as %s.',
        $this->renderAuthor(),
        $this->renderHandle($blocker_phid),
        $this->renderValue($new_name));
    } else if (!$old_closed && $new_closed) {
      return pht(
        '%s closed subtask %s as %s.',
        $this->renderAuthor(),
        $this->renderHandle($blocker_phid),
        $this->renderValue($new_name));
    } else {
      return pht(
        '%s changed the status of subtask %s from %s to %s.',
        $this->renderAuthor(),
        $this->renderHandle($blocker_phid),
        $this->renderValue($old_name),
        $this->renderValue($new_name));
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $blocker_phid = key($new);
    $old_status = head($old);
    $new_status = head($new);

    $old_closed = ManiphestTaskStatus::isClosedStatus($old_status);
    $new_closed = ManiphestTaskStatus::isClosedStatus($new_status);

    $old_name = ManiphestTaskStatus::getTaskStatusName($old_status);
    $new_name = ManiphestTaskStatus::getTaskStatusName($new_status);

    if ($old_closed && !$new_closed) {
      return pht(
        '%s reopened %s, a subtask of %s, as %s.',
        $this->renderAuthor(),
        $this->renderHandle($blocker_phid),
        $this->renderObject(),
        $this->renderValue($new_name));
    } else if (!$old_closed && $new_closed) {
      return pht(
        '%s closed %s, a subtask of %s, as %s.',
        $this->renderAuthor(),
        $this->renderHandle($blocker_phid),
        $this->renderObject(),
        $this->renderValue($new_name));
    } else {
      return pht(
        '%s changed the status of %s, a subtask of %s, '.
        'from %s to %s.',
        $this->renderAuthor(),
        $this->renderHandle($blocker_phid),
        $this->renderObject(),
        $this->renderValue($old_name),
        $this->renderValue($new_name));
    }
  }

  public function getIcon() {
    return 'fa-shield';
  }

  public function shouldHideForFeed() {
    // Hide "alice created X, a task blocking Y." from feed because it
    // will almost always appear adjacent to "alice created Y".
    $is_new = $this->getMetadataValue('blocker.new');
    if ($is_new) {
      return true;
    }

    return parent::shouldHideForFeed();
  }

  public function getRequiredCapabilities(
    $object,
    PhabricatorApplicationTransaction $xaction) {

    // When you close a task, we want to apply this transaction to its parents
    // even if you can not edit (or even see) those parents, so don't require
    // any capabilities. See PHI1059.

    return null;
  }
}
