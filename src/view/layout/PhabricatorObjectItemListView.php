<?php

final class PhabricatorObjectItemListView extends AphrontView {

  private $header;
  private $items;
  private $pager;
  private $stackable;
  private $cards;
  private $noDataString;
  private $flush;

  public function setFlush($flush) {
    $this->flush = $flush;
    return $this;
  }

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

  public function setStackable($stackable) {
    $this->stackable = $stackable;
    return $this;
  }

  public function setCards($cards) {
    $this->cards = $cards;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-object-item-list-view-css');

    $classes = array();
    $header = null;
    if (strlen($this->header)) {
      $header = phutil_tag(
        'h1',
        array(
          'class' => 'phabricator-object-item-list-header',
        ),
        $this->header);
    }

    if ($this->items) {
      $items = $this->items;
    } else {
      $string = nonempty($this->noDataString, pht('No data.'));
      $items = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NODATA)
        ->appendChild($string);
    }

    $pager = null;
    if ($this->pager) {
      $pager = $this->pager;
    }

    $classes[] = 'phabricator-object-item-list-view';
    if ($this->stackable) {
      $classes[] = 'phabricator-object-list-stackable';
    }
    if ($this->cards) {
      $classes[] = 'phabricator-object-list-cards';
    }
    if ($this->flush) {
      $classes[] = 'phabricator-object-list-flush';
    }

    return phutil_tag(
      'ul',
      array(
        'class' => implode(' ', $classes),
      ),
      array(
        $header,
        $items,
        $pager,
      ));
  }

}
