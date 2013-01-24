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
      throw new Exception("Call setSubmitURI() before render()!");
    }
    if (!$this->user) {
      throw new Exception("Call setUser() before render()!");
    }

    $content = phabricator_render_form(
      $this->user,
      array(
        'action'    => $this->uri,
        'method'    => 'POST',
        'sigil'     => 'inline-edit-form',
      ),
      $this->renderInputs().
      $this->renderBody());

    if ($this->onRight) {
      $core =
        '<th></th>'.
        '<td class="left"></td>'.
        '<th></th>'.
        '<td colspan="3" class="right3">'.$content.'</td>';
    } else {
      $core =
        '<th></th>'.
        '<td class="left">'.$content.'</td>'.
        '<th></th>'.
        '<td colspan="3" class="right3"></td>';
    }

    return '<table><tr class="inline-comment-splint">'.$core.'</tr></table>';
  }

  private function renderInputs() {
    $out = array();
    foreach ($this->inputs as $input) {
      list($name, $value) = $input;
      $out[] = phutil_render_tag(
        'input',
        array(
          'type'  => 'hidden',
          'name'  => $name,
          'value' => $value,
        ),
        null);
    }
    return implode('', $out);
  }

  private function renderBody() {
    $buttons = array();

    $buttons[] = '<button>Ready</button>';
    $buttons[] = javelin_render_tag(
      'button',
      array(
        'sigil' => 'inline-edit-cancel',
        'class' => 'grey',
      ),
      pht('Cancel'));

    $buttons = implode('', $buttons);

    $formatting = phutil_render_tag(
      'a',
      array(
        'href' => PhabricatorEnv::getDoclink(
          'article/Remarkup_Reference.html'),
        'tabindex' => '-1',
        'target' => '_blank',
      ),
      pht('Formatting Reference'));

    return javelin_render_tag(
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
      '<div class="differential-inline-comment-edit-title">'.
        phutil_escape_html($this->title).
      '</div>'.
      '<div class="differential-inline-comment-edit-body">'.
        $this->renderChildren().
      '</div>'.
      '<div class="differential-inline-comment-edit-buttons">'.
        $formatting.
        $buttons.
        '<div style="clear: both;"></div>'.
      '</div>');
  }

}
