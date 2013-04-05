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

    $panel = id(new PhabricatorWorkpanelView())
          ->setCards($list)
          ->setHeader('Business Stuff')
          ->setFooterAction(
            id(new PhabricatorMenuItemView())
              ->setName(pht('Add Task'))
              ->setIcon('new')
              ->setHref('/maniphest/task/create/'));

    $panel2 = id(new PhabricatorWorkpanelView())
          ->setCards($list2)
          ->setHeader('Under Duress');

    $panel3 = id(new PhabricatorWorkpanelView())
          ->setCards($list3)
          ->setHeader('Spicy Thai Chicken');

    $board = id(new PhabricatorWorkboardView())
          ->addPanel($panel)
          ->addPanel($panel2)
          ->addPanel($panel2)
          ->addPanel($panel3);

    $board2 = id(new PhabricatorWorkboardView())
          ->setFluidLayout(true)
          ->addPanel($panel)
          ->addPanel($panel2)
          ->addPanel($panel2)
          ->addPanel($panel)
          ->addPanel($panel2)
          ->addPanel($panel3);

    $action = new PhabricatorActionIconView();
    $action->setHref('/maniphest/task/create');
    $action->setImage('/rsrc/image/actions/edit.png');

    $person1 = new PhabricatorActionIconView();
    $person1->setHref('http://en.wikipedia.org/wiki/George_Washington');
    $person1->setImage(
      celerity_get_resource_uri('/rsrc/image/people/washington.png'));

    $person2 = new PhabricatorActionIconView();
    $person2->setHref('http://en.wikipedia.org/wiki/Warren_G._Harding');
    $person2->setImage(
      celerity_get_resource_uri('/rsrc/image/people/harding.png'));

    $person3 = new PhabricatorActionIconView();
    $person3->setHref('http://en.wikipedia.org/wiki/William_Howard_Taft');
    $person3->setImage(
      celerity_get_resource_uri('/rsrc/image/people/taft.png'));

    $board3 = id(new PhabricatorWorkboardView())
          ->setFluidLayout(true)
          ->addPanel($panel)
          ->addPanel($panel)
          ->addPanel($panel2)
          ->addPanel($panel3)
          ->addAction($action)
          ->addAction($person1)
          ->addAction($person2)
          ->addAction($person3);

    $head1 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Fixed Panel'));

    $head2 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Fluid Panel'));

    $head3 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Action Panel'));

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

    $wrap3 = phutil_tag(
      'div',
        array(
          'class' => 'ml'
        ),
        $board3);

    return phutil_tag(
      'div',
        array(),
        array(
          $head1,
          $wrap1,
          $head2,
          $wrap2,
          $head3,
          $wrap3
        ));
  }
}
