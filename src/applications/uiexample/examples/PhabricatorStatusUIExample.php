<?php

final class PhabricatorStatusUIExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Status List');
  }

  public function getDescription() {
    return pht(
      'Use %s to show relationships with objects.',
      phutil_tag('tt', array(), 'PHUIStatusListView'));
  }

  public function renderExample() {
    $out = array();

    $view = new PHUIStatusListView();

    $view->addItem(
      id(new PHUIStatusItemView())
        ->setIcon(PHUIStatusItemView::ICON_ACCEPT, 'green', pht('Yum'))
        ->setTarget(pht('Apple'))
        ->setNote(pht('You can eat them.')));

    $view->addItem(
      id(new PHUIStatusItemView())
        ->setIcon(PHUIStatusItemView::ICON_ADD, 'blue', pht('Has Peel'))
        ->setTarget(pht('Banana'))
        ->setNote(pht('Comes in bunches.'))
        ->setHighlighted(true));

    $view->addItem(
      id(new PHUIStatusItemView())
        ->setIcon(PHUIStatusItemView::ICON_WARNING, 'dark', pht('Caution'))
        ->setTarget(pht('Pomegranite'))
        ->setNote(pht('Lots of seeds. Watch out.')));

    $view->addItem(
      id(new PHUIStatusItemView())
        ->setIcon(PHUIStatusItemView::ICON_REJECT, 'red', pht('Bleh!'))
        ->setTarget(pht('Zucchini'))
        ->setNote(pht('Slimy and gross. Yuck!')));

    $out[] = id(new PHUIHeaderView())
      ->setHeader(pht('Fruit and Vegetable Status'));

    $out[] = id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->addPadding(PHUI::PADDING_LARGE)
      ->setBorder(true)
      ->appendChild($view);


    $view = new PHUIStatusListView();

    $manifest = array(
      PHUIStatusItemView::ICON_ACCEPT => 'PHUIStatusItemView::ICON_ACCEPT',
      PHUIStatusItemView::ICON_REJECT => 'PHUIStatusItemView::ICON_REJECT',
      PHUIStatusItemView::ICON_LEFT => 'PHUIStatusItemView::ICON_LEFT',
      PHUIStatusItemView::ICON_RIGHT => 'PHUIStatusItemView::ICON_RIGHT',
      PHUIStatusItemView::ICON_UP => 'PHUIStatusItemView::ICON_UP',
      PHUIStatusItemView::ICON_DOWN => 'PHUIStatusItemView::ICON_DOWN',
      PHUIStatusItemView::ICON_QUESTION => 'PHUIStatusItemView::ICON_QUESTION',
      PHUIStatusItemView::ICON_WARNING => 'PHUIStatusItemView::ICON_WARNING',
      PHUIStatusItemView::ICON_INFO => 'PHUIStatusItemView::ICON_INFO',
      PHUIStatusItemView::ICON_ADD => 'PHUIStatusItemView::ICON_ADD',
      PHUIStatusItemView::ICON_MINUS => 'PHUIStatusItemView::ICON_MINUS',
      PHUIStatusItemView::ICON_OPEN => 'PHUIStatusItemView::ICON_OPEN',
      PHUIStatusItemView::ICON_CLOCK => 'PHUIStatusItemView::ICON_CLOCK',
    );

    foreach ($manifest as $icon => $label) {

      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon($icon, 'indigo')
          ->setTarget($label));
    }

    $out[] = id(new PHUIHeaderView())
      ->setHeader(pht('All Icons'));

    $out[] = id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->addPadding(PHUI::PADDING_LARGE)
      ->setBorder(true)
      ->appendChild($view);

    return $out;
  }
}
