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
  private $isUndoTemplate;

  final public function setIsUndoTemplate($is_undo_template) {
    $this->isUndoTemplate = $is_undo_template;
    return $this;
  }

  final public function getIsUndoTemplate() {
    return $this->isUndoTemplate;
  }

  public function getInlineViews() {
    return $this->views;
  }

  public function addInlineView(PHUIDiffInlineCommentView $view) {
    $this->views[] = $view;
    return $this;
  }

  protected function getRowAttributes() {
    $is_undo_template = $this->getIsUndoTemplate();

    $is_hidden = false;
    if ($is_undo_template) {

      // NOTE: When this scaffold is turned into an "undo" template, it is
      // important it not have any metadata: the metadata reference will be
      // copied to each instance of the row. This is a complicated mess; for
      // now, just sneak by without generating metadata when rendering undo
      // templates.

      $metadata = null;
    } else {
      foreach ($this->getInlineViews() as $view) {
        if ($view->isHidden()) {
          $is_hidden = true;
        }
      }

      $metadata = array(
        'hidden' => $is_hidden,
      );
    }

    $classes = array();
    $classes[] = 'inline';
    if ($is_hidden) {
      $classes[] = 'inline-hidden';
    }

    $result = array(
      'class' => implode(' ', $classes),
      'sigil' => 'inline-row',
      'meta' => $metadata,
    );

    return $result;
  }

}
