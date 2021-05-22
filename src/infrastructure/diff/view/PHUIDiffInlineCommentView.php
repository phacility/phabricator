<?php

abstract class PHUIDiffInlineCommentView extends AphrontView {

  private $isOnRight;
  private $renderer;
  private $inlineComment;

  public function setInlineComment(PhabricatorInlineComment $comment) {
    $this->inlineComment = $comment;
    return $this;
  }

  public function getInlineComment() {
    return $this->inlineComment;
  }

  public function getIsOnRight() {
    return $this->isOnRight;
  }

  public function setIsOnRight($on_right) {
    $this->isOnRight = $on_right;
    return $this;
  }

  public function setRenderer($renderer) {
    $this->renderer = $renderer;
    return $this;
  }

  public function getRenderer() {
    return $this->renderer;
  }

  public function getScaffoldCellID() {
    return null;
  }

  public function isHidden() {
    return false;
  }

  public function isHideable() {
    return true;
  }

  public function newHiddenIcon() {
    if ($this->isHideable()) {
      return new PHUIDiffRevealIconView();
    } else {
      return null;
    }
  }

  protected function getInlineCommentMetadata() {
    $viewer = $this->getViewer();
    $inline = $this->getInlineComment();

    $is_synthetic = (bool)$inline->getSyntheticAuthor();

    $is_fixed = false;
    switch ($inline->getFixedState()) {
      case PhabricatorInlineComment::STATE_DONE:
      case PhabricatorInlineComment::STATE_DRAFT:
        $is_fixed = true;
        break;
    }

    $is_draft_done = false;
    switch ($inline->getFixedState()) {
      case PhabricatorInlineComment::STATE_DRAFT:
      case PhabricatorInlineComment::STATE_UNDRAFT:
        $is_draft_done = true;
        break;
    }

    return array(
      'id' => $inline->getID(),
      'phid' => $inline->getPHID(),
      'changesetID' => $inline->getChangesetID(),
      'number' => $inline->getLineNumber(),
      'length' => $inline->getLineLength(),
      'isNewFile' => (bool)$inline->getIsNewFile(),
      'replyToCommentPHID' => $inline->getReplyToCommentPHID(),
      'isDraft' => $inline->isDraft(),
      'isFixed' => $is_fixed,
      'isGhost' => $inline->getIsGhost(),
      'isSynthetic' => $is_synthetic,
      'isDraftDone' => $is_draft_done,
      'isEditing' => $inline->getIsEditing(),
      'documentEngineKey' => $inline->getDocumentEngineKey(),
      'startOffset' => $inline->getStartOffset(),
      'endOffset' => $inline->getEndOffset(),
      'on_right' => $this->getIsOnRight(),
      'state' => $inline->getContentStateMap(),
    );
  }


}
