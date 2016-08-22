<?php

final class PhabricatorGuideListView extends AphrontView {

  private $items = array();

  public function addItem(PhabricatorGuideItemView $item) {
    $this->items[] = $item;
    return $this;
  }

  public function render() {
    require_celerity_resource('guides-app-css');

    $list = id(new PHUIObjectItemListView())
      ->addClass('guides-app');

    foreach ($this->items as $item) {
      $icon = id(new PHUIIconView())
        ->setIcon($item->getIcon())
        ->setBackground($item->getIconBackground());

      $list_item = id(new PHUIObjectItemView())
        ->setHeader($item->getTitle())
        ->setHref($item->getHref())
        ->setImageIcon($icon)
        ->addAttribute($item->getDescription());

      $skip_href = $item->getSkipHref();
      if ($skip_href) {
        $skip = id(new PHUIButtonView())
          ->setText(pht('Skip'))
          ->setTag('a')
          ->setHref($skip_href)
          ->setColor(PHUIButtonView::GREY);
        $list_item->setLaunchButton($skip);
      }
      $list->addItem($list_item);
    }

    return $list;

  }

}
