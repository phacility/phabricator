<?php

final class PHUIInfoView extends AphrontTagView {

  const SEVERITY_ERROR = 'error';
  const SEVERITY_WARNING = 'warning';
  const SEVERITY_NOTICE = 'notice';
  const SEVERITY_NODATA = 'nodata';
  const SEVERITY_SUCCESS = 'success';
  const SEVERITY_PLAIN = 'plain';
  const SEVERITY_MFA = 'mfa';

  private $title;
  private $errors = array();
  private $severity = null;
  private $id;
  private $buttons = array();
  private $isHidden;
  private $flush;
  private $icon;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setSeverity($severity) {
    $this->severity = $severity;
    return $this;
  }

  private function getSeverity() {
    $severity = $this->severity ? $this->severity : self::SEVERITY_ERROR;
    return $severity;
  }

  public function setErrors(array $errors) {
    $this->errors = $errors;
    return $this;
  }

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function setIsHidden($is_hidden) {
    $this->isHidden = $is_hidden;
    return $this;
  }

  public function setFlush($flush) {
    $this->flush = $flush;
    return $this;
  }

  public function setIcon($icon) {
    if ($icon instanceof PHUIIconView) {
      $this->icon = $icon;
    } else {
      $icon = id(new PHUIIconView())
        ->setIcon($icon);
      $this->icon = $icon;
    }

    return $this;
  }

  private function getIcon() {
    if ($this->icon) {
      return $this->icon;
    }

    switch ($this->getSeverity()) {
      case self::SEVERITY_ERROR:
        $icon = 'fa-exclamation-circle';
        break;
      case self::SEVERITY_WARNING:
        $icon = 'fa-exclamation-triangle';
        break;
      case self::SEVERITY_NOTICE:
        $icon = 'fa-info-circle';
        break;
      case self::SEVERITY_PLAIN:
      case self::SEVERITY_NODATA:
        return null;
      case self::SEVERITY_SUCCESS:
        $icon = 'fa-check-circle';
        break;
      case self::SEVERITY_MFA:
        $icon = 'fa-lock';
        break;
    }

    $icon = id(new PHUIIconView())
      ->setIcon($icon)
      ->addClass('phui-info-icon');
    return $icon;
  }

  public function addButton(PHUIButtonView $button) {
    $this->buttons[] = $button;
    return $this;
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phui-info-view';
    $classes[] = 'phui-info-severity-'.$this->getSeverity();
    $classes[] = 'grouped';
    if ($this->flush) {
      $classes[] = 'phui-info-view-flush';
    }
    if ($this->getIcon()) {
      $classes[] = 'phui-info-has-icon';
    }

    return array(
      'id' => $this->id,
      'class' => implode(' ', $classes),
      'style' => $this->isHidden ? 'display: none;' : null,
    );
  }

  protected function getTagContent() {
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
      $list = head($this->errors);
    } else {
      $list = null;
    }

    $title = $this->title;
    if ($title !== null && strlen($title)) {
      $title = phutil_tag(
        'h1',
        array(
          'class' => 'phui-info-view-head',
        ),
        $title);
    } else {
      $title = null;
    }

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

    $icon = null;
    if ($this->getIcon()) {
      $icon = phutil_tag(
        'div',
        array(
          'class' => 'phui-info-view-icon',
        ),
        $this->getIcon());
    }

    return array(
      $icon,
      $buttons,
      $title,
      $body,
    );
  }
}
