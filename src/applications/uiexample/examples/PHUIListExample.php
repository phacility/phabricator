<?php

final class PHUIListExample extends PhabricatorUIExample {

  public function getName() {
    return 'Lists';
  }

  public function getDescription() {
    return 'Create a fanciful list of objects and prismatic donuts.';
  }

  public function renderExample() {


    /* Action Menu */

    $action1 = id(new PHUIListItemView())
      ->setName('Edit Document')
      ->setHref('#')
      ->setIcon('edit')
      ->setType(PHUIListItemView::TYPE_LINK);

    $action2 = id(new PHUIListItemView())
      ->setName('Move Document')
      ->setHref('#')
      ->setIcon('move')
      ->setType(PHUIListItemView::TYPE_LINK);

    $action3 = id(new PHUIListItemView())
      ->setName('Delete Document')
      ->setHref('#')
      ->setIcon('delete')
      ->setType(PHUIListItemView::TYPE_LINK);

    $action4 = id(new PHUIListItemView())
      ->setName('View History')
      ->setHref('#')
      ->setIcon('history')
      ->setType(PHUIListItemView::TYPE_LINK);

    $action5 = id(new PHUIListItemView())
      ->setName('Subscribe')
      ->setHref('#')
      ->setIcon('check')
      ->setType(PHUIListItemView::TYPE_LINK);

    $actionmenu = id(new PHUIListView())
      ->setType(PHUIListView::SIDENAV_LIST)
      ->addMenuItem($action1)
      ->addMenuItem($action2)
      ->addMenuItem($action3)
      ->addMenuItem($action4)
      ->addMenuItem($action5);


    /* Side Navigation */

    $label1 = id(new PHUIListItemView())
      ->setName('Getting Started')
      ->setType(PHUIListItemView::TYPE_LABEL);

    $label2 = id(new PHUIListItemView())
      ->setName('Documentation')
      ->setType(PHUIListItemView::TYPE_LABEL);

    $item1 = id(new PHUIListItemView())
      ->setName('Installation')
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $item2 = id(new PHUIListItemView())
      ->setName('Webserver Config')
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $item3 = id(new PHUIListItemView())
      ->setName('Adding Users')
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $item4 = id(new PHUIListItemView())
      ->setName('Debugging')
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $divider = id(new PHUIListItemView)
      ->setType(PHUIListItemView::TYPE_DIVIDER);

    $sidenav = id(new PHUIListView())
      ->setType(PHUIListView::SIDENAV_LIST)
      ->addMenuItem($label1)
      ->addMenuItem($item3)
      ->addMenuItem($item2)
      ->addMenuItem($item1)
      ->addMenuItem($item4)
      ->addMenuItem($divider)
      ->addMenuItem($label2)
      ->addMenuItem($item3)
      ->addMenuItem($item2)
      ->addMenuItem($item1)
      ->addMenuItem($item4);


    /* Unstyled */

    $item1 = id(new PHUIListItemView())
      ->setName('Rain');

    $item2 = id(new PHUIListItemView())
      ->setName('Spain');

    $item3 = id(new PHUIListItemView())
      ->setName('Mainly');

    $item4 = id(new PHUIListItemView())
      ->setName('Plains');

    $unstyled = id(new PHUIListView())
      ->addMenuItem($item1)
      ->addMenuItem($item2)
      ->addMenuItem($item3)
      ->addMenuItem($item4);

    /* Top Navigation */

    $home = id(new PHUIListItemView())
      ->setIcon('home')
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_ICON);

    $item1 = id(new PHUIListItemView())
      ->setName('Installation')
      ->setHref('#')
      ->setSelected(true)
      ->setType(PHUIListItemView::TYPE_LINK);

    $item2 = id(new PHUIListItemView())
      ->setName('Webserver Config')
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $item3 = id(new PHUIListItemView())
      ->setName('Adding Users')
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $item4 = id(new PHUIListItemView())
      ->setName('Debugging')
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $topnav = id(new PHUIListView())
      ->setType(PHUIListView::NAVBAR_LIST)
      ->addMenuItem($home)
      ->addMenuItem($item1)
      ->addMenuItem($item2)
      ->addMenuItem($item3)
      ->addMenuItem($item4);

    $layout1 =
      array(
        id(new PHUIBoxView())
          ->appendChild($unstyled)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->addPadding(PHUI::PADDING_SMALL)
          ->setShadow(true));

    $layout2 =
      array(
        id(new PHUIBoxView())
          ->appendChild($sidenav)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->setShadow(true));

    $layout3 =
      array(
        id(new PHUIBoxView())
          ->appendChild($topnav)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->setShadow(true));

    $layout4 =
      array(
        id(new PHUIBoxView())
          ->appendChild($actionmenu)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->setShadow(true));

    $head1 = id(new PHUIHeaderView())
      ->setHeader(pht('Unstyled'));

    $head2 = id(new PHUIHeaderView())
      ->setHeader(pht('Side Navigation'));

    $head3 = id(new PHUIHeaderView())
      ->setHeader(pht('Top Navigation'));

    $head4 = id(new PHUIHeaderView())
      ->setHeader(pht('Action Menu'));

    $wrap1 = id(new PHUIBoxView())
      ->appendChild($layout1)
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap2 = id(new PHUIBoxView())
      ->appendChild($layout2)
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap3 = id(new PHUIBoxView())
      ->appendChild($layout3)
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap4 = id(new PHUIBoxView())
      ->appendChild($layout4)
      ->addMargin(PHUI::MARGIN_LARGE);

    return phutil_tag(
      'div',
        array(
          'class' => 'phui-list-example',
        ),
        array(
          $head1,
          $wrap1,
          $head2,
          $wrap2,
          $head3,
          $wrap3,
          $head4,
          $wrap4
        ));
        }
}
