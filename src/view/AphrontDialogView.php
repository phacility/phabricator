<?php

final class AphrontDialogView extends AphrontView {

  private $title;
  private $submitButton;
  private $cancelURI;
  private $cancelText = 'Cancel';
  private $submitURI;
  private $hidden = array();
  private $class;
  private $renderAsForm = true;
  private $formID;

  private $width      = 'default';
  const WIDTH_DEFAULT = 'default';
  const WIDTH_FORM    = 'form';
  const WIDTH_FULL    = 'full';

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

  public function addSubmitButton($text = null) {
    if (!$text) {
      $text = pht('Okay');
    }

    $this->submitButton = $text;
    return $this;
  }

  public function addCancelButton($uri, $text = null) {
    if (!$text) {
      $text = pht('Cancel');
    }

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
      $buttons[] = javelin_tag(
        'button',
        array(
          'name' => '__submit__',
          'sigil' => '__default__',
        ),
        $this->submitButton);
    }

    if ($this->cancelURI) {
      $buttons[] = javelin_tag(
        'a',
        array(
          'href'  => $this->cancelURI,
          'class' => 'button grey',
          'name'  => '__cancel__',
          'sigil' => 'jx-workflow-button',
        ),
        $this->cancelText);
    }

    if (!$this->user) {
      throw new Exception(
        pht("You must call setUser() when rendering an AphrontDialogView."));
    }

    $more = $this->class;

    switch ($this->width) {
      case self::WIDTH_FORM:
      case self::WIDTH_FULL:
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
    $hidden_inputs[] = phutil_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => '__dialog__',
        'value' => '1',
      ));

    foreach ($this->hidden as $desc) {
      list($key, $value) = $desc;
      $hidden_inputs[] = javelin_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => $key,
          'value' => $value,
          'sigil' => 'aphront-dialog-application-input'
        ));
    }

    if (!$this->renderAsForm) {
      $buttons = array(phabricator_form(
        $this->user,
        $form_attributes,
        array_merge($hidden_inputs, $buttons)));
    }

    $buttons[] = phutil_tag('div', array('style' => 'clear: both;'), '');
    $children = $this->renderChildren();

    $content = hsprintf(
      '%s%s%s',
      phutil_tag('div', array('class' => 'aphront-dialog-head'), $this->title),
      phutil_tag('div', array('class' => 'aphront-dialog-body'), $children),
      phutil_tag('div', array('class' => 'aphront-dialog-tail'), $buttons));

    if ($this->renderAsForm) {
      return phabricator_form(
        $this->user,
        $form_attributes + $attributes,
        array($hidden_inputs, $content));
    } else {
      return javelin_tag(
        'div',
        $attributes,
        $content);
    }
  }

}
