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
    $inline = $this->inlineComment;
    $phid = $inline->getPHID();
    $id = $inline->getID();
    $user = $this->user;

    $is_draft = ($inline->getTransactionPHID() === null);
    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $inline,
      PhabricatorPolicyCapability::CAN_EDIT);

    if ($is_draft && $can_edit) {
      $draft = phutil_tag(
        'span',
        array(
          'class' => 'pholio-inline-status',
        ),
        pht('Not Submitted Yet'));

      $edit_action = javelin_tag(
        'a',
        array(
          'href' => '/pholio/inline/edit/'.$id.'/',
          'sigil' => 'inline-edit',
          'meta' => array(
            'phid' => $phid,
            'id' => $id,
          )
        ),
        pht('Edit'));

      $delete_action = javelin_tag(
        'a',
        array(
          'href' => '/pholio/inline/delete/'.$id.'/',
          'sigil' => 'inline-delete',
          'meta' => array(
            'phid' => $phid,
            'id' => $id,
          )
        ),
        pht('Delete'));

      $actions = phutil_tag(
        'span',
        array(
          'class' => 'pholio-inline-head-links'
        ),
        phutil_implode_html(
          " \xC2\xB7 ",
          array($draft, $edit_action, $delete_action)));
    }

    $comment_header = phutil_tag(
      'div',
      array(
        'class' => 'pholio-inline-comment-header'
      ),
      array($this->handle->getName(), $actions));


    $comment = $this->engine->renderOneObject(
      $inline,
      PholioTransactionComment::MARKUP_FIELD_COMMENT,
      $this->user);

    $comment_body = phutil_tag(
      'div',
      array(),
      $comment);

    $classes = array();
    $classes[] = 'pholio-inline-comment';

    if ($is_draft) {
      $classes[] = 'pholio-inline-comment-draft';
    }

    return javelin_tag(
      'div',
      array(
        'id' => "{$phid}_comment",
        'class' => implode(' ', $classes),
        'sigil' => 'inline_comment',
        'meta' => array(
          'phid' => $phid,
        )
      ),
      array($comment_header, $comment_body));
  }
}
