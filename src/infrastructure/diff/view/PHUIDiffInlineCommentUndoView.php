<?php

/**
 * Render the "Undo" action to recover discarded inline comments.
 *
 * This extends @{class:PHUIDiffInlineCommentView} so it can use the same
 * scaffolding code as other kinds of inline comments.
 */
final class PHUIDiffInlineCommentUndoView
  extends PHUIDiffInlineCommentView {

  private $isOnRight;

  public function setIsOnRight($is_on_right) {
    $this->isOnRight = $is_on_right;
    return $this;
  }

  public function getIsOnRight() {
    return $this->isOnRight;
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
      array('Changes discarded. ', $link));
  }

}
