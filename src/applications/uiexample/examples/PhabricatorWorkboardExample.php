<?php

final class PhabricatorWorkboardExample extends PhabricatorUIExample {

  public function getName() {
    return 'Workboard';
  }

  public function getDescription() {
    return 'A board for visualizing work. Fixed and Fluid layouts.';
  }

  public function renderExample() {

    /* List 1 */

    $list = new PhabricatorObjectItemListView();
    $list->setCards(true);
    $list->setFlush(true);

    $list->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('Business Card'))
        ->setBarColor('red'));
    $list->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('Playing Card'))
        ->setBarColor('orange'));
    $list->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('House of Cards'))
        ->setBarColor('yellow'));
    $list->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('Cardigan'))
        ->setBarColor('green'));
    $list->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('Cardamom'))
        ->addFootIcon('highlight-white', 'Spice')
        ->setBarColor('blue'));

    /* List 2 */

    $list2 = new PhabricatorObjectItemListView();
    $list2->setCards(true);
    $list2->setFlush(true);

    $list2->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('Business Card'))
        ->setBarColor('red'));
    $list2->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('Playing Card'))
        ->setBarColor('orange'));

    /* List 3 */

    $list3 = new PhabricatorObjectItemListView();
    $list3->setCards(true);
    $list3->setFlush(true);

    $list3->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('Business Card'))
        ->setBarColor('red'));
    $list3->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('Playing Card'))
        ->setBarColor('orange'));
    $list3->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('House of Cards'))
        ->setBarColor('yellow'));
    $list3->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('Cardigan'))
        ->setBarColor('green'));
    $list3->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('Cardamom'))
        ->addFootIcon('highlight-white', 'Spice')
        ->setBarColor('blue'));
    $list3->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('Business Card'))
        ->setBarColor('red'));
    $list3->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('Playing Card'))
        ->setBarColor('orange'));
    $list3->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('Business Card'))
        ->setBarColor('red'));
    $list3->addItem(
      id(new PhabricatorObjectItemView())
        ->setHeader(pht('Playing Card'))
        ->setBarColor('orange'));

    $panel = id(new PhabricatorWorkpanelView)
          ->setCards($list)
          ->setHeader('Business Stuff');

    $panel2 = id(new PhabricatorWorkpanelView)
          ->setCards($list2)
          ->setHeader('Under Duress');

    $panel3 = id(new PhabricatorWorkpanelView)
      ->setCards($list3)
      ->setHeader('Spicy Thai Chicken');

    $board = id(new PhabricatorWorkboardView)
          ->addPanel($panel)
          ->addPanel($panel2)
          ->addPanel($panel2)
          ->addPanel($panel3);

    $board2 = id(new PhabricatorWorkboardView)
          ->setFlexLayout(true)
          ->addPanel($panel)
          ->addPanel($panel2)
          ->addPanel($panel2)
          ->addPanel($panel2)
          ->addPanel($panel2)
          ->addPanel($panel3);

    $head1 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Fixed Panel'));

    $head2 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Fluid Panel'));


    $wrap1 = phutil_tag(
      'div',
        array(
          'class' => 'ml'
        ),
        $board);

    $wrap2 = phutil_tag(
      'div',
        array(
          'class' => 'ml'
        ),
        $board2);

    return phutil_tag(
      'div',
        array(),
        array(
          $head1,
          $wrap1,
          $head2,
          $wrap2
        ));
  }
}
