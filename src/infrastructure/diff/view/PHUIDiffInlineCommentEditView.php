<?php

final class PHUIDiffInlineCommentEditView
  extends PHUIDiffInlineCommentView {

  private $inputs = array();
  private $uri;
  private $title;
  private $number;
  private $length;
  private $renderer;
  private $isNewFile;
  private $replyToCommentPHID;
  private $changesetID;

  public function setIsNewFile($is_new_file) {
    $this->isNewFile = $is_new_file;
    return $this;
  }

  public function getIsNewFile() {
    return $this->isNewFile;
  }

  public function setRenderer($renderer) {
    $this->renderer = $renderer;
    return $this;
  }

  public function getRenderer() {
    return $this->renderer;
  }

  public function addHiddenInput($key, $value) {
    $this->inputs[] = array($key, $value);
    return $this;
  }

  public function setSubmitURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setReplyToCommentPHID($reply_to_phid) {
    $this->replyToCommentPHID = $reply_to_phid;
    return $this;
  }

  public function getReplyToCommentPHID() {
    return $this->replyToCommentPHID;
  }

  public function setChangesetID($changeset_id) {
    $this->changesetID = $changeset_id;
    return $this;
  }

  public function getChangesetID() {
    return $this->changesetID;
  }

  public function setNumber($number) {
    $this->number = $number;
    return $this;
  }

  public function setLength($length) {
    $this->length = $length;
    return $this;
  }

  public function render() {
    if (!$this->uri) {
      throw new PhutilInvalidStateException('setSubmitURI');
    }

    $viewer = $this->getViewer();

    $content = phabricator_form(
      $viewer,
      array(
        'action'    => $this->uri,
        'method'    => 'POST',
        'sigil'     => 'inline-edit-form',
      ),
      array(
        $this->renderInputs(),
        $this->renderBody(),
      ));

    return $content;
  }

  private function renderInputs() {
    $inputs = $this->inputs;
    $out = array();

    $inputs[] = array('on_right', (bool)$this->getIsOnRight());
    $inputs[] = array('replyToCommentPHID', $this->getReplyToCommentPHID());
    $inputs[] = array('renderer', $this->getRenderer());
    $inputs[] = array('changesetID', $this->getChangesetID());

    foreach ($inputs as $input) {
      list($name, $value) = $input;
      $out[] = phutil_tag(
        'input',
        array(
          'type'  => 'hidden',
          'name'  => $name,
          'value' => $value,
        ));
    }
    return $out;
  }

  private function renderBody() {
    $buttons = array();

    $buttons[] = id(new PHUIButtonView())
      ->setText(pht('Save Draft'));

    $buttons[] = id(new PHUIButtonView())
      ->setText(pht('Cancel'))
      ->setColor(PHUIButtonView::GREY)
      ->addSigil('inline-edit-cancel');

    $title = phutil_tag(
      'div',
      array(
        'class' => 'differential-inline-comment-edit-title',
      ),
      $this->title);

    $body = phutil_tag(
      'div',
      array(
        'class' => 'differential-inline-comment-edit-body',
      ),
      $this->renderChildren());

    $edit = phutil_tag(
      'div',
      array(
        'class' => 'differential-inline-comment-edit-buttons grouped',
      ),
      array(
        $buttons,
      ));

    return javelin_tag(
      'div',
      array(
        'class' => 'differential-inline-comment-edit',
        'sigil' => 'differential-inline-comment',
        'meta' => array(
          'changesetID' => $this->getChangesetID(),
          'on_right' => $this->getIsOnRight(),
          'isNewFile' => (bool)$this->getIsNewFile(),
          'number' => $this->number,
          'length' => $this->length,
          'replyToCommentPHID' => $this->getReplyToCommentPHID(),
        ),
      ),
      array(
        $title,
        $body,
        $edit,
      ));
  }

}
