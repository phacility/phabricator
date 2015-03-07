<?php

final class PHUIDiffInlineCommentEditView
  extends PHUIDiffInlineCommentView {

  private $inputs = array();
  private $uri;
  private $title;
  private $onRight;
  private $number;
  private $length;
  private $renderer;
  private $isNewFile;

  public function setIsNewFile($is_new_file) {
    $this->isNewFile = $is_new_file;
    return $this;
  }

  public function getIsNewFile() {
    return $this->isNewFile;
  }

  public function getIsOnRight() {
    return $this->onRight;
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

  public function setOnRight($on_right) {
    $this->onRight = $on_right;
    $this->addHiddenInput('on_right', $on_right);
    return $this;
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

    if ($this->renderer == '1up') {
      $cells = array(
        phutil_tag('th', array()),
        phutil_tag('th', array()),
        phutil_tag(
          'td',
          array('colspan' => 3, 'class' => 'right3'),
          $content),
      );
    } else {
      $cells = array(
        phutil_tag('th', array()),
        phutil_tag(
          'td',
          array('class' => 'left'),
          $this->onRight ? null : $content),
        phutil_tag('th', array()),
        phutil_tag(
          'td',
          array('colspan' => 3, 'class' => 'right3'),
          $this->onRight ? $content : null),
      );
    }

    $row = phutil_tag('tr', array('class' => 'inline-comment-splint'), $cells);
    return phutil_tag('table', array(), $row);
  }

  private function renderInputs() {
    $out = array();
    foreach ($this->inputs as $input) {
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

    $buttons[] = phutil_tag('button', array(), pht('Ready'));
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
        'class' => 'differential-inline-comment-edit-buttons',
      ),
      array(
        $buttons,
        phutil_tag('div', array('style' => 'clear: both'), ''),
      ));

    return javelin_tag(
      'div',
      array(
        'class' => 'differential-inline-comment-edit',
        'sigil' => 'differential-inline-comment',
        'meta' => array(
          'on_right' => $this->getIsOnRight(),
          'isNewFile' => (bool)$this->getIsNewFile(),
          'number' => $this->number,
          'length' => $this->length,
        ),
      ),
      array(
        $title,
        $body,
        $edit,
      ));
  }

}
