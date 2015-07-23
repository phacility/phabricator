<?php

final class PHUIPinboardView extends AphrontView {

  private $items = array();
  private $noDataString;

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function addItem(PHUIPinboardItemView $item) {
    $this->items[] = $item;
    return $this;
  }

  public function render() {
    require_celerity_resource('phui-pinboard-view-css');

    if (!$this->items) {
      $string = nonempty($this->noDataString, pht('No data.'));
      return id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->appendChild($string)
        ->render();
    }

    return phutil_tag(
      'ul',
      array(
        'class' => 'phui-pinboard-view',
      ),
      $this->items);
  }

}
