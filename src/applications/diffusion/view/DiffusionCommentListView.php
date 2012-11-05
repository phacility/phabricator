<?php

final class DiffusionCommentListView extends AphrontView {

  private $user;
  private $comments;
  private $inlineComments = array();
  private $pathMap = array();
  private $handles = array();
  private $markupEngine;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setComments(array $comments) {
    assert_instances_of($comments, 'PhabricatorAuditComment');
    $this->comments = $comments;
    return $this;
  }

  public function setInlineComments(array $inline_comments) {
    assert_instances_of($inline_comments, 'PhabricatorInlineCommentInterface');
    $this->inlineComments = $inline_comments;
    return $this;
  }

  public function setPathMap(array $path_map) {
    $this->pathMap = $path_map;
    return $this;
  }

  public function setMarkupEngine(PhabricatorMarkupEngine $markup_engine) {
    $this->markupEngine = $markup_engine;
    return $this;
  }

  public function getMarkupEngine() {
    return $this->markupEngine;
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    foreach ($this->comments as $comment) {
      $phids[$comment->getActorPHID()] = true;
      $metadata = $comment->getMetaData();

      $ccs_key = PhabricatorAuditComment::METADATA_ADDED_CCS;
      $added_ccs = idx($metadata, $ccs_key, array());
      foreach ($added_ccs as $cc) {
        $phids[$cc] = true;
      }
      $auditors_key = PhabricatorAuditComment::METADATA_ADDED_AUDITORS;
      $added_auditors = idx($metadata, $auditors_key, array());
      foreach ($added_auditors as $auditor) {
        $phids[$auditor] = true;
      }
    }
    foreach ($this->inlineComments as $comment) {
      $phids[$comment->getAuthorPHID()] = true;
    }
    return array_keys($phids);
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function render() {

    $inline_comments = mgroup($this->inlineComments, 'getAuditCommentID');

    $num = 1;

    $comments = array();
    foreach ($this->comments as $comment) {

      $inlines = idx($inline_comments, $comment->getID(), array());

      $view = id(new DiffusionCommentView())
        ->setMarkupEngine($this->getMarkupEngine())
        ->setComment($comment)
        ->setInlineComments($inlines)
        ->setCommentNumber($num)
        ->setHandles($this->handles)
        ->setPathMap($this->pathMap)
        ->setUser($this->user);

      $comments[] = $view->render();
      ++$num;
    }

    return
      '<div class="diffusion-comment-list">'.
        $this->renderSingleView($comments).
      '</div>';
  }

}
