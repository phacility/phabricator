<?php

final class DifferentialInlineCommentEditView extends AphrontView {

  private $inputs = array();
  private $uri;
  private $title;
  private $onRight;
  private $number;
  private $length;

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

    return phutil_tag('table', array(), phutil_tag(
      'tr',
      array('class' => 'inline-comment-splint'),
      array(
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
      )));
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
          'on_right' => $this->onRight,
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
