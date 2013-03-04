<?php

final class PholioInlineCommentView extends AphrontView {

  private $engine;
  private $handle;
  private $inlineComment;

  public function setEngine(PhabricatorMarkupEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  public function setHandle(PhabricatorObjectHandle $handle) {
     $this->handle = $handle;
     return $this;
  }

  public function setInlineComment(PholioTransactionComment $inline_comment) {
    if ($inline_comment->getImageID() === null) {
      throw new Exception("Comment provided is not inline comment");
    }

    $this->inlineComment = $inline_comment;
    return $this;
  }

  public function render() {
    if (!$this->inlineComment) {
      throw new Exception("Call setInlineComment() before render()!");
    }
    if ($this->user === null) {
      throw new Exception("Call setUser() before render()!");
    }
    if ($this->engine === null) {
      throw new Exception("Call setEngine() before render()!");
    }
    if ($this->handle === null) {
      throw new Exception("Call setHandle() before render()!");
    }

    $actions = null;

    if ($this->inlineComment->getTransactionPHID() === null &&
      $this->inlineComment->getEditPolicy(
        PhabricatorPolicyCapability::CAN_EDIT) == $this->user->getPHID()) {

      $edit_action = javelin_tag(
        'a',
        array(
          'href' => '/pholio/inline/edit/'.$this->inlineComment->getID(),
          'sigil' => 'inline-edit',
          'meta' => array(
            'phid' => $this->inlineComment->getPHID(),
            'id' => $this->inlineComment->getID()
          )
        ),
        pht('Edit'));

      $delete_action = javelin_tag(
        'a',
        array(
          'href' => '/pholio/inline/delete/'.$this->inlineComment->getID(),
          'sigil' => 'inline-delete',
          'meta' => array(
              'phid' => $this->inlineComment->getPHID(),
              'id' => $this->inlineComment->getID()
          )
        ),
        pht('Delete'));


      $actions = phutil_tag(
        'span',
        array(
          'class' => 'pholio-inline-head-links'
        ),
        array($edit_action, $delete_action));
    }

    $comment_header = phutil_tag(
      'div',
      array(
        'class' => 'pholio-inline-comment-header'
      ),
      array($this->handle->getName(), $actions));


    $comment = $this->engine->renderOneObject(
      $this->inlineComment,
      PholioTransactionComment::MARKUP_FIELD_COMMENT,
      $this->user);

    $comment_body = phutil_tag(
      'div',
      array(),
      $comment);

    $comment_block = javelin_tag(
      'div',
      array(
        'id' => $this->inlineComment->getPHID()."_comment",
        'class' => 'pholio-inline-comment',
        'sigil' => 'inline_comment',
        'meta' => array(
          'phid' => $this->inlineComment->getPHID()
        )
      ),
      array($comment_header, $comment_body));


    return $this->renderSingleView($comment_block);
  }
}
