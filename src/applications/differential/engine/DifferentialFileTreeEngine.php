<?php

final class DifferentialFileTreeEngine
  extends Phobject {

  private $viewer;
  private $changesets;
  private $disabled;

  public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function getIsVisible() {
    return (bool)$this->getSetting($this->getVisibleSettingKey());
  }

  public function setDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function getDisabled() {
    return $this->disabled;
  }

  public function setChangesets(array $changesets) {
    $this->changesets = $changesets;
    return $this;
  }

  public function getChangesets() {
    return $this->changesets;
  }

  public function newView($content) {
    if ($this->getDisabled()) {
      return $content;
    }

    $width = $this->getWidth();
    $is_visible = $this->getIsVisible();

    $formation_view = new PHUIFormationView();

    $flank_view = $formation_view->newFlankColumn()
      ->setHeaderText(pht('Affected Paths'))
      ->setIsResizable(true)
      ->setIsFixed(true)
      ->setIsVisible($is_visible)
      ->setWidth($width)
      ->setMinimumWidth($this->getMinimumWidth())
      ->setMaximumWidth($this->getMaximumWidth());

    $viewer = $this->getViewer();
    if ($viewer->isLoggedIn()) {
      $flank_view
        ->setVisibleSettingKey($this->getVisibleSettingKey())
        ->setWidthSettingKey($this->getWidthSettingKey());
    }

    $flank_view->setHead(
      array(
        phutil_tag('div', array(),
          array(
            id(new PHUIIconView())->setIcon('fa-list'),
            pht('Table of Contents'),
            '[t]',
          )),
      ));

    $flank_view->setBody(
      phutil_tag(
        'div',
        array(
          'class' => 'phui-flank-loading',
        ),
        pht('Loading...')));

    $flank_view->setTail(
      array(
        phutil_tag('div', array(),
          array(
            id(new PHUIIconView())->setIcon('fa-chevron-left'),
            pht('Hide Panel'),
            '[f]',
          )),
        phutil_tag(
          'div',
          array(),
          array(
            id(new PHUIIconView())->setIcon('fa-keyboard-o'),
            pht('Keyboard Reference'),
            '[?]',
          )),
      ));

    $main_column = $formation_view->newContentColumn()
      ->appendChild($content);

    return $formation_view;
  }

  private function getVisibleSettingKey() {
    return PhabricatorFiletreeVisibleSetting::SETTINGKEY;
  }

  private function getWidthSettingKey() {
    return PhabricatorFiletreeWidthSetting::SETTINGKEY;
  }

  private function getWidth() {
    $width = (int)$this->getSetting($this->getWidthSettingKey());

    if (!$width) {
      $width = $this->getDefaultWidth();
    }

    $min = $this->getMinimumWidth();
    if ($width < $min) {
      $width = $min;
    }

    $max = $this->getMaximumWidth();
    if ($width > $max) {
      $width = $max;
    }

    return $width;
  }

  private function getDefaultWidth() {
    return 240;
  }

  private function getMinimumWidth() {
    return 150;
  }

  private function getMaximumWidth() {
    return 512;
  }

  private function getSetting($key) {
    $viewer = $this->getViewer();
    return $viewer->getUserSetting($key);
  }


}
