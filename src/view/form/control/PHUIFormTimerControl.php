<?php

final class PHUIFormTimerControl extends AphrontFormControl {

  private $icon;

  public function setIcon(PHUIIconView $icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  protected function getCustomControlClass() {
    return 'phui-form-timer';
  }

  protected function renderInput() {
    $icon_cell = phutil_tag(
      'td',
      array(
        'class' => 'phui-form-timer-icon',
      ),
      $this->getIcon());

    $content_cell = phutil_tag(
      'td',
      array(
        'class' => 'phui-form-timer-content',
      ),
      $this->renderChildren());

    $row = phutil_tag('tr', array(), array($icon_cell, $content_cell));

    return phutil_tag('table', array(), $row);
  }

}
