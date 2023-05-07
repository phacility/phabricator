<?php

final class PHUIObjectItemListView extends AphrontTagView {

  private $header;
  private $items;
  private $pager;
  private $noDataString;
  private $flush;
  private $simple;
  private $big;
  private $drag;
  private $allowEmptyList;
  private $itemClass = 'phui-oi-standard';
  private $tail = array();

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

  public function setDrag($drag) {
    $this->drag = $drag;
    $this->setItemClass('phui-oi-drag');
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

  public function newTailButton() {
    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setColor(PHUIButtonView::GREY)
      ->setIcon('fa-chevron-down')
      ->setText(pht('View All Results'));

    $this->tail[] = $button;

    return $button;
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phui-oi-list-view';

    if ($this->flush) {
      $classes[] = 'phui-oi-list-flush';
      require_celerity_resource('phui-oi-flush-ui-css');
    }

    if ($this->simple) {
      $classes[] = 'phui-oi-list-simple';
      require_celerity_resource('phui-oi-simple-ui-css');
    }

    if ($this->big) {
      $classes[] = 'phui-oi-list-big';
      require_celerity_resource('phui-oi-big-ui-css');
    }

    if ($this->drag) {
      $classes[] = 'phui-oi-list-drag';
      require_celerity_resource('phui-oi-drag-ui-css');
    }

    return array(
      'class' => $classes,
    );
  }

  protected function getTagContent() {
    $viewer = $this->getUser();
    require_celerity_resource('phui-oi-list-view-css');
    require_celerity_resource('phui-oi-color-css');

    $header = null;
    if ($this->header !== null && strlen($this->header)) {
      $header = phutil_tag(
        'h1',
        array(
          'class' => 'phui-oi-list-header',
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
    } else if ($this->getAllowEmptyList()) {
      $items = null;
    } else {
      $string = nonempty($this->noDataString, pht('No data.'));
      $string = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->appendChild($string);
      $items = phutil_tag(
        'li',
        array(
          'class' => 'phui-oi-empty',
        ),
        $string);

    }

    $pager = null;
    if ($this->pager) {
      $pager = $this->pager;
    }

    $tail = array();
    foreach ($this->tail as $tail_item) {
      $tail[] = phutil_tag(
        'li',
        array(
          'class' => 'phui-oi-tail',
        ),
        $tail_item);
    }

    return array(
      $header,
      $items,
      $tail,
      $pager,
      $this->renderChildren(),
    );
  }

}
