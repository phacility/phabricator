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
      throw new Exception('Call setSubmitURI() before render()!');
    }
    if (!$this->user) {
      throw new Exception('Call setUser() before render()!');
    }

    $content = phabricator_form(
      $this->user,
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

    $buttons[] = phutil_tag('button', array(), pht('Save Draft'));
    $buttons[] = javelin_tag(
      'button',
      array(
        'sigil' => 'inline-edit-cancel',
        'class' => 'grey',
      ),
      pht('Cancel'));

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
