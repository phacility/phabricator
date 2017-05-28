<?php

final class ManiphestTaskAttachTransaction
  extends ManiphestTaskTransactionType {

  // NOTE: this type is deprecated. Keep it around for legacy installs
  // so any transactions render correctly.

  const TRANSACTIONTYPE = 'attach';

  public function getActionName() {
    return pht('Attached');
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $old = nonempty($old, array());
    $new = nonempty($new, array());
    $new = array_keys(idx($new, 'FILE', array()));
    $old = array_keys(idx($old, 'FILE', array()));

    $added = array_diff($new, $old);
    $removed = array_diff($old, $new);
    if ($added && !$removed) {
      return pht(
        '%s attached %s file(s): %s.',
        $this->renderAuthor(),
        phutil_count($added),
        $this->renderHandleList($added));
    } else if ($removed && !$added) {
      return pht(
        '%s detached %s file(s): %s.',
        $this->renderAuthor(),
        phutil_count($removed),
        $this->renderHandleList($removed));
    } else {
      return pht(
        '%s changed file(s), attached %s: %s; detached %s: %s.',
        $this->renderAuthor(),
        phutil_count($added),
        $this->renderHandleList($added),
        phutil_count($removed),
        $this->renderHandleList($removed));
    }

  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $old = nonempty($old, array());
    $new = nonempty($new, array());
    $new = array_keys(idx($new, 'FILE', array()));
    $old = array_keys(idx($old, 'FILE', array()));

    $added = array_diff($new, $old);
    $removed = array_diff($old, $new);
    if ($added && !$removed) {
      return pht(
        '%s attached %d file(s) of %s: %s',
        $this->renderAuthor(),
        $this->renderObject(),
        count($added),
        $this->renderHandleList($added));
    } else if ($removed && !$added) {
      return pht(
        '%s detached %d file(s) of %s: %s',
        $this->renderAuthor(),
        $this->renderObject(),
        count($removed),
        $this->renderHandleList($removed));
    } else {
      return pht(
        '%s changed file(s) for %s, attached %d: %s; detached %d: %s',
        $this->renderAuthor(),
        $this->renderObject(),
        count($added),
        $this->renderHandleList($added),
        count($removed),
        $this->renderHandleList($removed));
    }
  }

  public function getIcon() {
    return 'fa-thumb-tack';
  }

}
