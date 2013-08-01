<?php

final class PhabricatorUIStatusExample extends PhabricatorUIExample {

  public function getName() {
    return 'Status List';
  }

  public function getDescription() {
    return hsprintf(
      'Use <tt>PHUIStatusListView</tt> to show relationships with objects.');
  }

  public function renderExample() {

    $out = array();

    $view = new PHUIStatusListView();

    $view->addItem(
      id(new PHUIStatusItemView())
        ->setIcon('accept-green', pht('Yum'))
        ->setTarget(pht('Apple'))
        ->setNote(pht('You can eat them.')));

    $view->addItem(
      id(new PHUIStatusItemView())
        ->setIcon('add-blue', pht('Has Peel'))
        ->setTarget(pht('Banana'))
        ->setNote(pht('Comes in bunches.'))
        ->setHighlighted(true));

    $view->addItem(
      id(new PHUIStatusItemView())
        ->setIcon('warning-dark', pht('Caution'))
        ->setTarget(pht('Pomegranite'))
        ->setNote(pht('Lots of seeds. Watch out.')));

    $view->addItem(
      id(new PHUIStatusItemView())
        ->setIcon('reject-red', pht('Bleh!'))
        ->setTarget(pht('Zucchini'))
        ->setNote(pht('Slimy and gross. Yuck!')));

    $out[] = id(new PhabricatorHeaderView())
      ->setHeader(pht('Fruit and Vegetable Status'));

    $out[] = id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->addPadding(PHUI::PADDING_LARGE)
      ->setShadow(true)
      ->appendChild($view);


    $view = new PHUIStatusListView();

    $manifest = PHUIIconView::getSheetManifest(PHUIIconView::SPRITE_STATUS);

    foreach ($manifest as $sprite) {
      $name = substr($sprite['name'], strlen('status-'));

      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon($name)
          ->setTarget($name));
    }

    $out[] = id(new PhabricatorHeaderView())
      ->setHeader(pht('All Icons'));

    $out[] = id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->addPadding(PHUI::PADDING_LARGE)
      ->setShadow(true)
      ->appendChild($view);

    return $out;
  }
}
