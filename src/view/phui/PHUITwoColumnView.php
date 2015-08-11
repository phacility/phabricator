<?php

final class PHUITwoColumnView extends AphrontTagView {

  private $mainColumn;
  private $sideColumn;

  public function setMainColumn($main) {
    $this->mainColumn = $main;
    return $this;
  }

  public function setSideColumn($side) {
    $this->sideColumn = $side;
    return $this;
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'phui-two-column-view grouped',
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phui-two-column-view-css');

    $main = phutil_tag(
      'div',
      array(
        'class' => 'phui-main-column',
      ),
      $this->mainColumn);

    $side = phutil_tag(
      'div',
      array(
        'class' => 'phui-side-column',
      ),
      $this->sideColumn);

      return phutil_tag_div(
        'phui-two-column-row',
        array(
          $main,
          $side,
        ));
  }
}
