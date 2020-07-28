<?php

final class FuelHandleListItemView
  extends FuelView {

  private $handle;

  public function setHandle(PhabricatorObjectHandle $handle) {
    $this->handle = $handle;
    return $this;
  }

  public function render() {
    $cells = array();

    $cells[] = phutil_tag(
      'div',
      array(
        'class' => 'fuel-handle-list-item-cell fuel-handle-list-item-icon',
      ),
      $this->newIconView());

    $cells[] = phutil_tag(
      'div',
      array(
        'class' => 'fuel-handle-list-item-cell fuel-handle-list-item-handle',
      ),
      $this->newHandleView());

    $cells[] = phutil_tag(
      'div',
      array(
        'class' => 'fuel-handle-list-item-cell fuel-handle-list-item-note',
      ),
      $this->newNoteView());

    return phutil_tag(
      'div',
      array(
        'class' => 'fuel-handle-list-item',
      ),
      $cells);
  }


  private function newIconView() {
    $icon_icon = null;
    $icon_image = null;
    $icon_color = null;

    $handle = $this->handle;
    if ($handle) {
      $icon_image = $handle->getImageURI();
      if (!$icon_image) {
        $icon_icon = $handle->getIcon();
        $icon_color = $handle->getIconColor();
      }
    }

    if ($icon_image === null && $icon_icon === null) {
      return null;
    }

    $view = new PHUIIconView();

    if ($icon_image !== null) {
      $view->setImage($icon_image);
    } else {
      if ($icon_color === null) {
        $icon_color = 'bluegrey';
      }

      if ($icon_icon !== null) {
        $view->setIcon($icon_icon);
      }

      if ($icon_color !== null) {
        $view->setColor($icon_color);
      }
    }


    return $view;
  }

  private function newHandleView() {
    $handle = $this->handle;
    if ($handle) {
      return $handle->renderLink();
    }

    return null;
  }

  private function newNoteView() {
    return null;
  }

}
