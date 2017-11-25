<?php

final class ManiphestTaskStatusTransaction
  extends ManiphestTaskTransactionType {

  const TRANSACTIONTYPE = 'status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function shouldHide() {
    if ($this->getOldValue() === null) {
      return true;
    } else {
      return false;
    }
  }

  public function getActionStrength() {
    return 1.3;
  }

  public function getActionName() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

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
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

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
            $this->renderAuthor(),
            $this->renderHandle($commit_phid));
        } else {
          return pht(
            '%s closed this task as a duplicate.',
            $this->renderAuthor());
        }
      } else {
        if ($commit_phid) {
          return pht(
            '%s closed this task as %s by committing %s.',
            $this->renderAuthor(),
            $this->renderValue($new_name),
            $this->renderHandle($commit_phid));
        } else {
          return pht(
            '%s closed this task as %s.',
            $this->renderAuthor(),
            $this->renderValue($new_name));
        }
      }
    } else if (!$new_closed && $old_closed) {
      if ($commit_phid) {
        return pht(
          '%s reopened this task as %s by committing %s.',
          $this->renderAuthor(),
          $this->renderValue($new_name),
          $this->renderHandle($commit_phid));
      } else {
        return pht(
          '%s reopened this task as %s.',
          $this->renderAuthor(),
          $this->renderValue($new_name));
      }
    } else {
      if ($commit_phid) {
        return pht(
          '%s changed the task status from %s to %s by committing %s.',
          $this->renderAuthor(),
          $this->renderValue($old_name),
          $this->renderValue($new_name),
          $this->renderHandle($commit_phid));
      } else {
        return pht(
          '%s changed the task status from %s to %s.',
          $this->renderAuthor(),
          $this->renderValue($old_name),
          $this->renderValue($new_name));
      }
    }

  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

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
            $this->renderAuthor(),
            $this->renderObject(),
            $this->renderHandle($commit_phid));
        } else {
          return pht(
            '%s closed %s as a duplicate.',
            $this->renderAuthor(),
            $this->renderObject());
        }
      } else {
        if ($commit_phid) {
          return pht(
            '%s closed %s as %s by committing %s.',
            $this->renderAuthor(),
            $this->renderObject(),
            $this->renderValue($new_name),
            $this->renderHandle($commit_phid));
        } else {
          return pht(
            '%s closed %s as %s.',
            $this->renderAuthor(),
            $this->renderObject(),
            $this->renderValue($new_name));
        }
      }
    } else if (!$new_closed && $old_closed) {
      if ($commit_phid) {
        return pht(
          '%s reopened %s as %s by committing %s.',
          $this->renderAuthor(),
          $this->renderObject(),
          $this->renderValue($new_name),
          $this->renderHandle($commit_phid));
      } else {
        return pht(
          '%s reopened %s as "%s".',
          $this->renderAuthor(),
          $this->renderObject(),
          $new_name);
      }
    } else {
      if ($commit_phid) {
        return pht(
          '%s changed the status of %s from %s to %s by committing %s.',
          $this->renderAuthor(),
          $this->renderObject(),
          $this->renderValue($old_name),
          $this->renderValue($new_name),
          $this->renderHandle($commit_phid));
      } else {
        return pht(
          '%s changed the status of %s from %s to %s.',
          $this->renderAuthor(),
          $this->renderObject(),
          $this->renderValue($old_name),
          $this->renderValue($new_name));
      }
    }
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $action = ManiphestTaskStatus::getStatusIcon($new);
    if ($action !== null) {
      return $action;
    }

    if (ManiphestTaskStatus::isClosedStatus($new)) {
      return 'fa-check';
    } else {
      return 'fa-pencil';
    }
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $color = ManiphestTaskStatus::getStatusColor($new);
    if ($color !== null) {
      return $color;
    }

    if (ManiphestTaskStatus::isOpenStatus($new)) {
      return 'green';
    } else {
      return 'indigo';
    }

  }

  public function getTransactionTypeForConduit($xaction) {
    return 'status';
  }

  public function getFieldValuesForConduit($xaction, $data) {
    return array(
      'old' => $xaction->getOldValue(),
      'new' => $xaction->getNewValue(),
    );
  }

}
