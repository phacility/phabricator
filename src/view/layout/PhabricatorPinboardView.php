<?php

final class PhabricatorPinboardView extends AphrontView {

  private $items = array();
  private $noDataString;

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function addItem(PhabricatorPinboardItemView $item) {
    $this->items[] = $item;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-pinboard-view-css');

    if (!$this->items) {
      $string = nonempty($this->noDataString, pht('No data.'));
      return id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NODATA)
        ->appendChild($string)
        ->render();
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-pinboard-view',
      ),
      $this->items);
  }

}
