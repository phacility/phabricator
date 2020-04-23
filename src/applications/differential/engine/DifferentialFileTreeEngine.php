<?php

final class DifferentialFileTreeEngine
  extends Phobject {

  private $viewer;
  private $changesets;
  private $disabled;
  private $ownedChangesets;

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

    require_celerity_resource('diff-tree-view-css');

    $width = $this->getWidth();
    $is_visible = $this->getIsVisible();

    $formation_view = new PHUIFormationView();

    $flank_view = $formation_view->newFlankColumn()
      ->setHeaderText(pht('Paths'))
      ->setIsResizable(true)
      ->setIsFixed(true)
      ->setIsVisible($is_visible)
      ->setIsDesktopOnly(true)
      ->setWidth($width)
      ->setMinimumWidth($this->getMinimumWidth())
      ->setMaximumWidth($this->getMaximumWidth());

    $viewer = $this->getViewer();
    if ($viewer->isLoggedIn()) {
      $flank_view
        ->setExpanderTooltip(pht('Show Paths Panel'))
        ->setVisibleSettingKey($this->getVisibleSettingKey())
        ->setWidthSettingKey($this->getWidthSettingKey());
    }

    $head_view = id(new PHUIListView())
      ->addMenuItem(
        id(new PHUIListItemView())
          ->setIcon('fa-list')
          ->setName(pht('Table of Contents'))
          ->setKeyCommand('t')
          ->setHref('#'));
    $flank_view->setHead($head_view);

    $tail_view = id(new PHUIListView());

    if ($viewer->isLoggedIn()) {
      $tail_view->addMenuItem(
        id(new PHUIListItemView())
          ->setIcon('fa-comment-o')
          ->setName(pht('Add Comment'))
          ->setKeyCommand('x')
          ->setHref('#'));
    }

    $tail_view
      ->addMenuItem(
        id(new PHUIListItemView())
          ->setIcon('fa-chevron-left')
          ->setName(pht('Hide Panel'))
          ->setKeyCommand('f')
          ->setHref('#'))
      ->addMenuItem(
        id(new PHUIListItemView())
          ->setIcon('fa-keyboard-o')
          ->setName(pht('Keyboard Reference'))
          ->setKeyCommand('?')
          ->setHref('#'));
    $flank_view->setTail($tail_view);

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
