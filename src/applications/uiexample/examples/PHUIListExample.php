<?php

final class PHUIListExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Lists');
  }

  public function getDescription() {
    return pht('Create a fanciful list of objects and prismatic donuts.');
  }

  public function renderExample() {
    /* Action Menu */

    $action1 = id(new PHUIListItemView())
      ->setName(pht('Edit Document'))
      ->setHref('#')
      ->setIcon('fa-pencil')
      ->setType(PHUIListItemView::TYPE_LINK);

    $action2 = id(new PHUIListItemView())
      ->setName(pht('Move Document'))
      ->setHref('#')
      ->setIcon('fa-arrows')
      ->setType(PHUIListItemView::TYPE_LINK);

    $action3 = id(new PHUIListItemView())
      ->setName(pht('Delete Document'))
      ->setHref('#')
      ->setIcon('fa-times')
      ->setType(PHUIListItemView::TYPE_LINK);

    $action4 = id(new PHUIListItemView())
      ->setName(pht('View History'))
      ->setHref('#')
      ->setIcon('fa-list')
      ->setType(PHUIListItemView::TYPE_LINK);

    $action5 = id(new PHUIListItemView())
      ->setName(pht('Subscribe'))
      ->setHref('#')
      ->setIcon('fa-plus-circle')
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
      ->setName(pht('Getting Started'))
      ->setType(PHUIListItemView::TYPE_LABEL);

    $label2 = id(new PHUIListItemView())
      ->setName(pht('Documentation'))
      ->setType(PHUIListItemView::TYPE_LABEL);

    $item1 = id(new PHUIListItemView())
      ->setName(pht('Installation'))
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $item2 = id(new PHUIListItemView())
      ->setName(pht('Webserver Config'))
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $item3 = id(new PHUIListItemView())
      ->setName(pht('Adding Users'))
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $item4 = id(new PHUIListItemView())
      ->setName(pht('Debugging'))
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $divider = id(new PHUIListItemView())
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
      ->setName(pht('Rain'));

    $item2 = id(new PHUIListItemView())
      ->setName(pht('Spain'));

    $item3 = id(new PHUIListItemView())
      ->setName(pht('Mainly'));

    $item4 = id(new PHUIListItemView())
      ->setName(pht('Plains'));

    $unstyled = id(new PHUIListView())
      ->addMenuItem($item1)
      ->addMenuItem($item2)
      ->addMenuItem($item3)
      ->addMenuItem($item4);

    /* Top Navigation */

    $home = id(new PHUIListItemView())
      ->setIcon('fa-home')
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_ICON);

    $item1 = id(new PHUIListItemView())
      ->setName(pht('Installation'))
      ->setHref('#')
      ->setSelected(true)
      ->setType(PHUIListItemView::TYPE_LINK);

    $item2 = id(new PHUIListItemView())
      ->setName(pht('Webserver Config'))
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $item3 = id(new PHUIListItemView())
      ->setName(pht('Adding Users'))
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $item4 = id(new PHUIListItemView())
      ->setName(pht('Debugging'))
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

        $item1 = id(new PHUIListItemView())
      ->setName(pht('Installation'))
      ->setHref('#')
      ->setSelected(true)
      ->setType(PHUIListItemView::TYPE_LINK);

    $item2 = id(new PHUIListItemView())
      ->setName(pht('Webserver Config'))
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $details1 = id(new PHUIListItemView())
      ->setName(pht('Details'))
      ->setHref('#')
      ->setSelected(true)
      ->setType(PHUIListItemView::TYPE_LINK);

    $details2 = id(new PHUIListItemView())
      ->setName(pht('Lint (OK)'))
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $details3 = id(new PHUIListItemView())
      ->setName(pht('Unit (5/5)'))
      ->setHref('#')
      ->setType(PHUIListItemView::TYPE_LINK);

    $details4 = id(new PHUIListItemView())
      ->setName(pht('Lint (Warn)'))
      ->setHref('#')
      ->setStatusColor(PHUIListItemView::STATUS_WARN)
      ->setType(PHUIListItemView::TYPE_LINK);

    $details5 = id(new PHUIListItemView())
      ->setName(pht('Unit (3/5)'))
      ->setHref('#')
      ->setStatusColor(PHUIListItemView::STATUS_FAIL)
      ->setType(PHUIListItemView::TYPE_LINK);

    $topnav = id(new PHUIListView())
      ->setType(PHUIListView::NAVBAR_LIST)
      ->addMenuItem($home)
      ->addMenuItem($item1)
      ->addMenuItem($item2)
      ->addMenuItem($item3)
      ->addMenuItem($item4);

    $statustabs = id(new PHUIListView())
      ->setType(PHUIListView::NAVBAR_LIST)
      ->addMenuItem($details1)
      ->addMenuItem($details2)
      ->addMenuItem($details3)
      ->addMenuItem($details4)
      ->addMenuItem($details5);

    $layout1 =
      array(
        id(new PHUIBoxView())
          ->appendChild($unstyled)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->addPadding(PHUI::PADDING_SMALL)
          ->setBorder(true),
      );

    $layout2 =
      array(
        id(new PHUIBoxView())
          ->appendChild($sidenav)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->setBorder(true),
      );

    $layout3 =
      array(
        id(new PHUIBoxView())
          ->appendChild($topnav)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->setBorder(true),
      );

    $layout4 =
      array(
        id(new PHUIBoxView())
          ->appendChild($actionmenu)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->setBorder(true),
      );

    $layout5 =
      array(
        id(new PHUIBoxView())
          ->appendChild($statustabs)
          ->addMargin(PHUI::MARGIN_MEDIUM)
          ->setBorder(true),
      );

    $head1 = id(new PHUIHeaderView())
      ->setHeader(pht('Unstyled'));

    $head2 = id(new PHUIHeaderView())
      ->setHeader(pht('Side Navigation'));

    $head3 = id(new PHUIHeaderView())
      ->setHeader(pht('Top Navigation'));

    $head4 = id(new PHUIHeaderView())
      ->setHeader(pht('Action Menu'));

    $head5 = id(new PHUIHeaderView())
      ->setHeader(pht('Status Tabs'));

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

    $wrap5 = id(new PHUIBoxView())
      ->appendChild($layout5)
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
          $head5,
          $wrap5,
          $head4,
          $wrap4,
        ));
  }
}
