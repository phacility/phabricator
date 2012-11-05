<?php

final class AphrontDialogView extends AphrontView {

  private $title;
  private $submitButton;
  private $cancelURI;
  private $cancelText = 'Cancel';
  private $submitURI;
  private $user;
  private $hidden = array();
  private $class;
  private $renderAsForm = true;
  private $formID;

  private $width      = 'default';
  const WIDTH_DEFAULT = 'default';
  const WIDTH_FORM    = 'form';

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setSubmitURI($uri) {
    $this->submitURI = $uri;
    return $this;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  public function addSubmitButton($text = 'Okay') {
    $this->submitButton = $text;
    return $this;
  }

  public function addCancelButton($uri, $text = 'Cancel') {
    $this->cancelURI = $uri;
    $this->cancelText = $text;
    return $this;
  }

  public function addHiddenInput($key, $value) {
    if (is_array($value)) {
      foreach ($value as $hidden_key => $hidden_value) {
        $this->hidden[] = array($key.'['.$hidden_key.']', $hidden_value);
      }
    } else {
      $this->hidden[] = array($key, $value);
    }
    return $this;
  }

  public function setClass($class) {
    $this->class = $class;
    return $this;
  }

  public function setRenderDialogAsDiv() {
    // TODO: This API is awkward.
    $this->renderAsForm = false;
    return $this;
  }

  public function setFormID($id) {
    $this->formID = $id;
    return $this;
  }

  public function setWidth($width) {
    $this->width = $width;
    return $this;
  }

  final public function render() {
    require_celerity_resource('aphront-dialog-view-css');

    $buttons = array();
    if ($this->submitButton) {
      $buttons[] = javelin_render_tag(
        'button',
        array(
          'name' => '__submit__',
          'sigil' => '__default__',
        ),
        phutil_escape_html($this->submitButton));
    }

    if ($this->cancelURI) {
      $buttons[] = javelin_render_tag(
        'a',
        array(
          'href'  => $this->cancelURI,
          'class' => 'button grey',
          'name'  => '__cancel__',
          'sigil' => 'jx-workflow-button',
        ),
        phutil_escape_html($this->cancelText));
    }
    $buttons = implode('', $buttons);

    if (!$this->user) {
      throw new Exception(
        "You must call setUser() when rendering an AphrontDialogView.");
    }

    $more = $this->class;

    switch ($this->width) {
      case self::WIDTH_FORM:
        $more .= ' aphront-dialog-view-width-'.$this->width;
        break;
      case self::WIDTH_DEFAULT:
        break;
      default:
        throw new Exception("Unknown dialog width '{$this->width}'!");
    }

    $attributes = array(
      'class'   => 'aphront-dialog-view '.$more,
      'sigil'   => 'jx-dialog',
    );

    $form_attributes = array(
      'action'  => $this->submitURI,
      'method'  => 'post',
      'id'      => $this->formID,
    );

    $hidden_inputs = array();
    foreach ($this->hidden as $desc) {
      list($key, $value) = $desc;
      $hidden_inputs[] = javelin_render_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => $key,
          'value' => $value,
          'sigil' => 'aphront-dialog-application-input'
        ));
    }
    $hidden_inputs = implode("\n", $hidden_inputs);
    $hidden_inputs =
      '<input type="hidden" name="__dialog__" value="1" />'.
      $hidden_inputs;


    if (!$this->renderAsForm) {
      $buttons = phabricator_render_form(
        $this->user,
        $form_attributes,
        $hidden_inputs.$buttons);
    }

    $content =
      '<div class="aphront-dialog-head">'.
        phutil_escape_html($this->title).
      '</div>'.
      '<div class="aphront-dialog-body">'.
        $this->renderChildren().
      '</div>'.
      '<div class="aphront-dialog-tail">'.
        $buttons.
        '<div style="clear: both;"></div>'.
      '</div>';

    if ($this->renderAsForm) {
      return phabricator_render_form(
        $this->user,
        $form_attributes + $attributes,
        $hidden_inputs.
        $content);
    } else {
      return javelin_render_tag(
        'div',
        $attributes,
        $content);
    }
  }

}
