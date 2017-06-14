<?php

final class ManiphestTaskPriorityTransaction
  extends ManiphestTaskTransactionType {

  const TRANSACTIONTYPE = 'priority';

  public function generateOldValue($object) {
    if ($this->isNewObject()) {
      return null;
    }
    return $object->getPriority();
  }

  public function generateNewValue($object, $value) {
    // `$value` is supposed to be a keyword, but if the priority
    // assigned to a task has been removed from the config,
    // no such keyword will be available. Other edits to the task
    // should still be allowed, even if the priority is  no longer
    // valid, so treat this as a no-op.
    if ($value === ManiphestTaskPriority::UNKNOWN_PRIORITY_KEYWORD) {
      return $object->getPriority();
    }

    return (string)ManiphestTaskPriority::getTaskPriorityFromKeyword($value);
  }

  public function applyInternalEffects($object, $value) {
    $object->setPriority($value);
  }

  public function getActionStrength() {
    return 1.1;
  }

  public function getActionName() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old == ManiphestTaskPriority::getDefaultPriority()) {
      return pht('Triaged');
    } else if ($old > $new) {
      return pht('Lowered Priority');
    } else {
      return pht('Raised Priority');
    }
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $old_name = ManiphestTaskPriority::getTaskPriorityName($old);
    $new_name = ManiphestTaskPriority::getTaskPriorityName($new);

    if ($old == ManiphestTaskPriority::getDefaultPriority()) {
      return pht(
        '%s triaged this task as %s priority.',
        $this->renderAuthor(),
        $this->renderValue($new_name));
    } else if ($old > $new) {
      return pht(
        '%s lowered the priority of this task from %s to %s.',
        $this->renderAuthor(),
        $this->renderValue($old_name),
        $this->renderValue($new_name));
    } else {
      return pht(
        '%s raised the priority of this task from %s to %s.',
        $this->renderAuthor(),
        $this->renderValue($old_name),
        $this->renderValue($new_name));
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $old_name = ManiphestTaskPriority::getTaskPriorityName($old);
    $new_name = ManiphestTaskPriority::getTaskPriorityName($new);

    if ($old == ManiphestTaskPriority::getDefaultPriority()) {
      return pht(
        '%s triaged %s as %s priority.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderValue($new_name));
    } else if ($old > $new) {
      return pht(
        '%s lowered the priority of %s from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderValue($old_name),
        $this->renderValue($new_name));
    } else {
      return pht(
        '%s raised the priority of %s from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderValue($old_name),
        $this->renderValue($new_name));
    }
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old == ManiphestTaskPriority::getDefaultPriority()) {
      return 'fa-arrow-right';
    } else if ($old > $new) {
      return 'fa-arrow-down';
    } else {
      return 'fa-arrow-up';
    }
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old == ManiphestTaskPriority::getDefaultPriority()) {
      return 'green';
    } else if ($old > $new) {
      return 'grey';
    } else {
      return 'yellow';
    }
  }

}
