<?php

final class PHUIWorkboardExample extends PhabricatorUIExample {

  public function getName() {
    return 'Workboard';
  }

  public function getDescription() {
    return 'A board for visualizing work. Fixed and Fluid layouts.';
  }

  public function renderExample() {

    /* List 1 */

    $list = new PHUIObjectItemListView();
    $list->setFlush(true);

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Business Card'))
        ->setBarColor('red'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Playing Card'))
        ->setBarColor('orange'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('House of Cards'))
        ->setBarColor('yellow'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Cardigan'))
        ->setBarColor('green'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Cardamom'))
        ->addFootIcon('highlight-white', 'Spice')
        ->setBarColor('blue'));

    /* List 2 */

    $list2 = new PHUIObjectItemListView();
    $list2->setFlush(true);

    $list2->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Business Card'))
        ->setBarColor('red'));
    $list2->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Playing Card'))
        ->setBarColor('orange'));

    /* List 3 */

    $list3 = new PHUIObjectItemListView();
    $list3->setFlush(true);

    $list3->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Business Card'))
        ->setBarColor('red'));
    $list3->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Playing Card'))
        ->setBarColor('orange'));
    $list3->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('House of Cards'))
        ->setBarColor('yellow'));
    $list3->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Cardigan'))
        ->setBarColor('green'));
    $list3->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Cardamom'))
        ->addFootIcon('highlight-white', 'Spice')
        ->setBarColor('blue'));
    $list3->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Business Card'))
        ->setBarColor('red'));
    $list3->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Playing Card'))
        ->setBarColor('orange'));
    $list3->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Business Card'))
        ->setBarColor('red'));
    $list3->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Playing Card'))
        ->setBarColor('orange'));

    $panel = id(new PHUIWorkpanelView())
          ->setHeader('Business Stuff')
          ->setFooterAction(
            id(new PHUIListItemView())
              ->setName(pht('Add Task'))
              ->setIcon('new')
              ->setHref('/maniphest/task/create/'));

    $panel2 = id(new PHUIWorkpanelView())
          ->setHeader('Under Duress');

    $panel3 = id(new PHUIWorkpanelView())
          ->setHeader('Spicy Thai Chicken');

    $board = id(new PHUIWorkboardView())
          ->addPanel($panel)
          ->addPanel($panel2)
          ->addPanel($panel2)
          ->addPanel($panel3);

    $board2 = id(new PHUIWorkboardView())
          ->setFluidLayout(true)
          ->addPanel($panel)
          ->addPanel($panel2)
          ->addPanel($panel2)
          ->addPanel($panel)
          ->addPanel($panel2)
          ->addPanel($panel3);

    $action = new PHUIIconView();
    $action->setHref('/maniphest/task/create');
    $action->setImage('/rsrc/image/actions/edit.png');

    $person1 = new PHUIIconView();
    $person1->setHref('http://en.wikipedia.org/wiki/George_Washington');
    $person1->setImage(
      celerity_get_resource_uri('/rsrc/image/people/washington.png'));

    $person2 = new PHUIIconView();
    $person2->setHref('http://en.wikipedia.org/wiki/Warren_G._Harding');
    $person2->setImage(
      celerity_get_resource_uri('/rsrc/image/people/harding.png'));

    $person3 = new PHUIIconView();
    $person3->setHref('http://en.wikipedia.org/wiki/William_Howard_Taft');
    $person3->setImage(
      celerity_get_resource_uri('/rsrc/image/people/taft.png'));

    $board3 = id(new PHUIWorkboardView())
          ->setFluidLayout(true)
          ->addPanel($panel)
          ->addPanel($panel)
          ->addPanel($panel2)
          ->addPanel($panel3)
          ->addAction($action)
          ->addAction($person1)
          ->addAction($person2)
          ->addAction($person3);

    $head1 = id(new PHUIHeaderView())
      ->setHeader(pht('Fixed Panel'));

    $head2 = id(new PHUIHeaderView())
      ->setHeader(pht('Fluid Panel'));

    $head3 = id(new PHUIHeaderView())
      ->setHeader(pht('Action Panel'));

    $wrap1 = id(new PHUIBoxView())
      ->appendChild($board)
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap2 = id(new PHUIBoxView())
      ->appendChild($board2)
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap3 = id(new PHUIBoxView())
      ->appendChild($board3)
      ->addMargin(PHUI::MARGIN_LARGE);

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
