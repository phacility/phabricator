<?php

final class PHUIFormTimerControl extends AphrontFormControl {

  private $icon;
  private $updateURI;

  public function setIcon(PHUIIconView $icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function setUpdateURI($update_uri) {
    $this->updateURI = $update_uri;
    return $this;
  }

  public function getUpdateURI() {
    return $this->updateURI;
  }

  protected function getCustomControlClass() {
    return 'phui-form-timer';
  }

  protected function renderInput() {
    return $this->newTimerView();
  }

  public function newTimerView() {
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

    $node_id = null;

    $update_uri = $this->getUpdateURI();
    if ($update_uri) {
      $node_id = celerity_generate_unique_node_id();

      Javelin::initBehavior(
        'phui-timer-control',
        array(
          'nodeID' => $node_id,
          'uri' => $update_uri,
        ));
    }

    return phutil_tag('table', array('id' => $node_id), $row);
  }

}
