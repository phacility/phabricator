<?php

final class PHUIObjectItemListView extends AphrontTagView {

  private $header;
  private $items;
  private $pager;
  private $noDataString;
  private $flush;
  private $simple;
  private $big;
  private $allowEmptyList;
  private $itemClass = 'phui-object-item-standard';

  public function setAllowEmptyList($allow_empty_list) {
    $this->allowEmptyList = $allow_empty_list;
    return $this;
  }

  public function getAllowEmptyList() {
    return $this->allowEmptyList;
  }

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

  public function setSimple($simple) {
    $this->simple = $simple;
    return $this;
  }

  public function setBig($big) {
    $this->big = $big;
    return $this;
  }

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function addItem(PHUIObjectItemView $item) {
    $this->items[] = $item;
    return $this;
  }

  public function setItemClass($item_class) {
    $this->itemClass = $item_class;
    return $this;
  }

  protected function getTagName() {
    return 'ul';
  }

  protected function getTagAttributes() {
    $classes = array();

    $classes[] = 'phui-object-item-list-view';
    if ($this->flush) {
      $classes[] = 'phui-object-list-flush';
    }
    if ($this->simple) {
      $classes[] = 'phui-object-list-simple';
    }
    if ($this->big) {
      $classes[] = 'phui-object-list-big';
    }

    return array(
      'class' => $classes,
    );
  }

  protected function getTagContent() {
    $viewer = $this->getUser();
    require_celerity_resource('phui-object-item-list-view-css');

    $header = null;
    if (strlen($this->header)) {
      $header = phutil_tag(
        'h1',
        array(
          'class' => 'phui-object-item-list-header',
        ),
        $this->header);
    }

    if ($this->items) {
      if ($viewer) {
        foreach ($this->items as $item) {
          $item->setUser($viewer);
        }
      }

      foreach ($this->items as $item) {
        $item->addClass($this->itemClass);
      }

      $items = $this->items;
    } else if ($this->allowEmptyList) {
      $items = null;
    } else {
      $string = nonempty($this->noDataString, pht('No data.'));
      $string = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->appendChild($string);
      $items = phutil_tag(
        'li',
        array(
          'class' => 'phui-object-item-empty',
        ),
        $string);

    }

    $pager = null;
    if ($this->pager) {
      $pager = $this->pager;
    }

    return array(
      $header,
      $items,
      $pager,
      $this->renderChildren(),
    );
  }

}
