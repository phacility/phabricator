<?php

/**
 * Wraps an inline comment in a table row.
 *
 * Inline comments need different wrapping cells when shown in unified vs
 * side-by-side diffs, as the two tables have different layouts. This wraps
 * an inline comment element in an appropriate table row.
 */
abstract class PHUIDiffInlineCommentRowScaffold extends AphrontView {

  private $views = array();

  public function getInlineViews() {
    return $this->views;
  }

  public function addInlineView(PHUIDiffInlineCommentView $view) {
    $this->views[] = $view;
    return $this;
  }

}
