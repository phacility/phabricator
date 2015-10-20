<?php

final class PHUIInfoView extends AphrontView {

  const SEVERITY_ERROR = 'error';
  const SEVERITY_WARNING = 'warning';
  const SEVERITY_NOTICE = 'notice';
  const SEVERITY_NODATA = 'nodata';
  const SEVERITY_SUCCESS = 'success';

  private $title;
  private $errors;
  private $severity;
  private $id;
  private $buttons = array();
  private $isHidden;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setSeverity($severity) {
    $this->severity = $severity;
    return $this;
  }

  public function setErrors(array $errors) {
    $this->errors = $errors;
    return $this;
  }

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function setIsHidden($bool) {
    $this->isHidden = $bool;
    return $this;
  }

  public function addButton(PHUIButtonView $button) {

    $this->buttons[] = $button;
    return $this;
  }

  public function render() {
    require_celerity_resource('phui-info-view-css');

    $errors = $this->errors;
    if (count($errors) > 1) {
      $list = array();
      foreach ($errors as $error) {
        $list[] = phutil_tag(
          'li',
          array(),
          $error);
      }
      $list = phutil_tag(
        'ul',
        array(
          'class' => 'phui-info-view-list',
        ),
        $list);
    } else if (count($errors) == 1) {
      $list = $this->errors[0];
    } else {
      $list = null;
    }

    $title = $this->title;
    if (strlen($title)) {
      $title = phutil_tag(
        'h1',
        array(
          'class' => 'phui-info-view-head',
        ),
        $title);
    } else {
      $title = null;
    }

    $this->severity = nonempty($this->severity, self::SEVERITY_ERROR);

    $classes = array();
    $classes[] = 'phui-info-view';
    $classes[] = 'phui-info-severity-'.$this->severity;
    $classes[] = 'grouped';
    $classes = implode(' ', $classes);

    $children = $this->renderChildren();
    if ($list) {
      $children[] = $list;
    }

    $body = null;
    if (!empty($children)) {
      $body = phutil_tag(
        'div',
        array(
          'class' => 'phui-info-view-body',
        ),
        $children);
    }

    $buttons = null;
    if (!empty($this->buttons)) {
      $buttons = phutil_tag(
        'div',
        array(
          'class' => 'phui-info-view-actions',
        ),
        $this->buttons);
    }

    return phutil_tag(
      'div',
      array(
        'id' => $this->id,
        'class' => $classes,
        'style' => $this->isHidden ? 'display: none;' : null,
      ),
      array(
        $buttons,
        $title,
        $body,
      ));
  }
}
