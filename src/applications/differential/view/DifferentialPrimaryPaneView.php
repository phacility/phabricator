<?php

final class DifferentialPrimaryPaneView extends AphrontView {

  private $id;

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function render() {

    return phutil_tag(
      'div',
      array(
        'class' => 'differential-primary-pane',
        'id'    => $this->id,
      ),
      $this->renderChildren());
  }

}
