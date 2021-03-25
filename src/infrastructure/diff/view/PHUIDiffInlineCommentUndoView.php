<?php

/**
 * Render the "Undo" action to recover discarded inline comments.
 *
 * This extends @{class:PHUIDiffInlineCommentView} so it can use the same
 * scaffolding code as other kinds of inline comments.
 */
final class PHUIDiffInlineCommentUndoView
  extends PHUIDiffInlineCommentView {

  public function isHideable() {
    return false;
  }

  public function render() {
    $link = javelin_tag(
      'a',
      array(
        'href'  => '#',
        'sigil' => 'differential-inline-comment-undo',
      ),
      pht('Undo'));

    return phutil_tag(
      'div',
      array(
        'class' => 'differential-inline-undo',
      ),
      array(pht('Changes discarded.'), ' ', $link));
  }

}
