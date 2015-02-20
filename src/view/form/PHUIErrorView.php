<?php

final class PHUIErrorView extends AphrontView {

  const SEVERITY_ERROR = 'error';
  const SEVERITY_WARNING = 'warning';
  const SEVERITY_NOTICE = 'notice';
  const SEVERITY_NODATA = 'nodata';

  private $title;
  private $errors;
  private $severity;
  private $id;

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

  final public function render() {

    require_celerity_resource('phui-error-view-css');

    $errors = $this->errors;
    if ($errors) {
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
          'class' => 'phui-error-view-list',
        ),
        $list);
    } else {
      $list = null;
    }

    $title = $this->title;
    if (strlen($title)) {
      $title = phutil_tag(
        'h1',
        array(
          'class' => 'phui-error-view-head',
        ),
        $title);
    } else {
      $title = null;
    }

    $this->severity = nonempty($this->severity, self::SEVERITY_ERROR);

    $classes = array();
    $classes[] = 'phui-error-view';
    $classes[] = 'phui-error-severity-'.$this->severity;
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
          'class' => 'phui-error-view-body',
        ),
        $children);
    }

    return phutil_tag(
      'div',
      array(
        'id' => $this->id,
        'class' => $classes,
      ),
      array(
        $title,
        $body,
      ));
  }
}
