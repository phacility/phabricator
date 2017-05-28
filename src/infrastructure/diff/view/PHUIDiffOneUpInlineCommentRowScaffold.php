<?php

/**
 * Row scaffold for `1up` (unified) changeset views.
 *
 * This scaffold is straightforward.
 */
final class PHUIDiffOneUpInlineCommentRowScaffold
  extends PHUIDiffInlineCommentRowScaffold {

  public function render() {
    $inlines = $this->getInlineViews();
    if (count($inlines) != 1) {
      throw new Exception(
        pht('One-up inline row scaffold must have exactly one inline view!'));
    }
    $inline = head($inlines);

    $attrs = array(
      'colspan' => 3,
      'class' => 'right3',
      'id' => $inline->getScaffoldCellID(),
    );

    if ($inline->getIsOnRight()) {
      $left_hidden = null;
      $right_hidden = $inline->newHiddenIcon();
    } else {
      $left_hidden = $inline->newHiddenIcon();
      $right_hidden = null;
    }

    $cells = array(
      phutil_tag('th', array(), $left_hidden),
      phutil_tag('th', array(), $right_hidden),
      phutil_tag('td', $attrs, $inline),
    );

    return javelin_tag('tr', $this->getRowAttributes(), $cells);
  }

}
