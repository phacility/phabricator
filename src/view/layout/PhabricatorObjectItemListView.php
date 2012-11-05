<?php

final class PhabricatorObjectItemListView extends AphrontView {

  private $header;
  private $items;
  private $pager;
  private $noDataString;

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setPager($pager) {
    $this->pager = $pager;
    return $this;
  }

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function addItem(PhabricatorObjectItemView $item) {
    $this->items[] = $item;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-object-item-list-view-css');

    $header = phutil_render_tag(
      'h1',
      array(
        'class' => 'phabricator-object-item-list-header',
      ),
      phutil_escape_html($this->header));

    if ($this->items) {
      $items = $this->renderSingleView($this->items);
    } else {
      $string = nonempty($this->noDataString, pht('No data.'));
      $items = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NODATA)
        ->appendChild(phutil_escape_html($string))
        ->render();
    }

    $pager = null;
    if ($this->pager) {
      $pager = $this->renderSingleView($this->pager);
    }

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-object-item-list-view',
      ),
      $header.$items.$pager);
  }

}
