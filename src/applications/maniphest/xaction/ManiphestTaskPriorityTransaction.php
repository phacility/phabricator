<?php

final class ManiphestTaskPriorityTransaction
  extends ManiphestTaskTransactionType {

  const TRANSACTIONTYPE = 'priority';

  public function generateOldValue($object) {
    return (string)$object->getPriority();
  }

  public function generateNewValue($object, $value) {
    // `$value` is supposed to be a keyword, but if the priority
    // assigned to a task has been removed from the config,
    // no such keyword will be available. Other edits to the task
    // should still be allowed, even if the priority is no longer
    // valid, so treat this as a no-op.
    if ($value === ManiphestTaskPriority::UNKNOWN_PRIORITY_KEYWORD) {
      return (string)$object->getPriority();
    }

    return (string)ManiphestTaskPriority::getTaskPriorityFromKeyword($value);
  }

  public function applyInternalEffects($object, $value) {
    $object->setPriority($value);
  }

  public function getActionStrength() {
    return 110;
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

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $content_source = $this->getEditor()->getContentSource();
    $is_web = ($content_source instanceof PhabricatorWebContentSource);

    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();

      // If this is a legitimate keyword like "low" or "high", this transaction
      // is fine and apply normally.
      $keyword = ManiphestTaskPriority::getTaskPriorityFromKeyword($value);
      if ($keyword !== null) {
        continue;
      }

      // If this is the magic "don't change things" value for editing tasks
      // with an obsolete priority constant in the database, let it through if
      // this is a web edit.
      if ($value === ManiphestTaskPriority::UNKNOWN_PRIORITY_KEYWORD) {
        if ($is_web) {
          continue;
        }
      }

      $keyword_list = array();
      foreach (ManiphestTaskPriority::getTaskPriorityMap() as $pri => $name) {
        $keyword = ManiphestTaskPriority::getKeywordForTaskPriority($pri);
        if ($keyword === null) {
          continue;
        }
        $keyword_list[] = $keyword;
      }

      $errors[] = $this->newInvalidError(
        pht(
          'Task priority "%s" is not a valid task priority. Use a priority '.
          'keyword to choose a task priority: %s.',
          $value,
          implode(', ', $keyword_list)),
        $xaction);
    }

    return $errors;
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'priority';
  }

  public function getFieldValuesForConduit($xaction, $data) {
    $old = $xaction->getOldValue();
    if ($old !== null) {
      $old = (int)$old;
      $old_name = ManiphestTaskPriority::getTaskPriorityName($old);
    } else {
      $old_name = null;
    }

    $new = $xaction->getNewValue();
    $new = (int)$new;
    $new_name = ManiphestTaskPriority::getTaskPriorityName($new);

    return array(
      'old' => array(
        'value' => $old,
        'name' => $old_name,
      ),
      'new' => array(
        'value' => $new,
        'name' => $new_name,
      ),
    );
  }

}
