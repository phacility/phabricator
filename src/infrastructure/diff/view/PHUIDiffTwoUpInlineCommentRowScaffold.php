<?php

/**
 * Row scaffold for 2up (side-by-side) changeset views.
 *
 * Although this scaffold is normally straightforward, it may also accept
 * two inline comments and display them adjacently.
 */
final class PHUIDiffTwoUpInlineCommentRowScaffold
  extends PHUIDiffInlineCommentRowScaffold {

  public function render() {
    $inlines = $this->getInlineViews();

    if (!$inlines) {
      throw new Exception(
        pht('Two-up inline row scaffold must have at least one inline view.'));
    }

    if (count($inlines) > 2) {
      throw new Exception(
        pht('Two-up inline row scaffold must have at most two inline views.'));
    }

    if (count($inlines) == 1) {
      $inline = head($inlines);
      if ($inline->getIsOnRight()) {
        $left_side = null;
        $right_side = $inline;

        $left_hidden = null;
        $right_hidden = $inline->newHiddenIcon();
      } else {
        $left_side = $inline;
        $right_side = null;

        $left_hidden = $inline->newHiddenIcon();
        $right_hidden = null;
      }
    } else {
      list($u, $v) = $inlines;

      if ($u->getIsOnRight() == $v->getIsOnRight()) {
        throw new Exception(
          pht(
            'Two-up inline row scaffold must have one comment on the left and '.
            'one comment on the right when showing two comments.'));
      }

      if ($v->getIsOnRight()) {
        $left_side = $u;
        $right_side = $v;
      } else {
        $left_side = $v;
        $right_side = $u;
      }

      $left_hidden = null;
      $right_hidden = null;
    }

    $left_attrs = array(
      'class' => 'left',
      'id' => ($left_side ? $left_side->getScaffoldCellID() : null),
    );

    $right_attrs = array(
      'colspan' => 2,
      'id' => ($right_side ? $right_side->getScaffoldCellID() : null),
    );

    $cells = array(
      phutil_tag('td', array('class' => 'n'), $left_hidden),
      phutil_tag('td', $left_attrs, $left_side),
      phutil_tag('td', array('class' => 'n'), $right_hidden),
      phutil_tag('td', array('class' => 'copy')),
      phutil_tag('td', $right_attrs, $right_side),
    );

    return javelin_tag('tr', $this->getRowAttributes(), $cells);
  }

}
