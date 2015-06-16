<?php

final class PHUIDocumentExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Document View');
  }

  public function getDescription() {
    return pht('Useful for areas of large content navigation');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $action = id(new PHUIListItemView())
      ->setName(pht('Actions'))
      ->setType(PHUIListItemView::TYPE_LABEL);

    $action1 = id(new PHUIListItemView())
      ->setName(pht('Edit Document'))
      ->setHref('#')
      ->setIcon('fa-edit')
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

    $divider = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_DIVIDER);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Installation'));

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

    $sidenav = id(new PHUIListView())
      ->setType(PHUIListView::SIDENAV_LIST)
      ->addMenuItem($action)
      ->addMenuItem($action1)
      ->addMenuItem($action2)
      ->addMenuItem($action3)
      ->addMenuItem($action4)
      ->addMenuItem($action5)
      ->addMenuItem($divider)
      ->addMenuItem($label1)
      ->addMenuItem($item1)
      ->addMenuItem($item2)
      ->addMenuItem($item3)
      ->addMenuItem($item4)
      ->addMenuItem($label2)
      ->addMenuItem($item2)
      ->addMenuItem($item3)
      ->addMenuItem($item4)
      ->addMenuItem($item1);

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

    $topnav = id(new PHUIListView())
      ->setType(PHUIListView::NAVBAR_LIST)
      ->addMenuItem($home)
      ->addMenuItem($item1)
      ->addMenuItem($item2)
      ->addMenuItem($item3)
      ->addMenuItem($item4);

    $document = hsprintf(
      '<p class="pl">Lorem ipsum dolor sit amet, consectetur adipisicing, '.
      'sed do eiusmod tempor incididunt ut labore et dolore magna '.
      'aliqua. Ut enim ad minim veniam, quis nostrud exercitation '.
      'ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis '.
      'aute irure dolor in reprehenderit in voluptate velit esse cillum '.
      'dolore eu fugiat nulla pariatur. Excepteur sint occaecat '.
      'cupidatat non proident, sunt in culpa qui officia deserunt '.
      'mollit anim id est laborum.</p>'.
      '<p class="plr pll plb">Lorem ipsum dolor sit amet, consectetur, '.
      'sed do eiusmod tempor incididunt ut labore et dolore magna '.
      'aliqua. Ut enim ad minim veniam, quis nostrud exercitation '.
      'ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis '.
      'aute irure dolor in reprehenderit in voluptate velit esse cillum '.
      'dolore eu fugiat nulla pariatur. Excepteur sint occaecat '.
      'cupidatat non proident, sunt in culpa qui officia deserunt '.
      'mollit anim id est laborum.</p>'.
      '<p class="plr pll plb">Lorem ipsum dolor sit amet, consectetur, '.
      'sed do eiusmod tempor incididunt ut labore et dolore magna '.
      'aliqua. Ut enim ad minim veniam, quis nostrud exercitation '.
      'ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis '.
      'aute irure dolor in reprehenderit in voluptate velit esse cillum '.
      'dolore eu fugiat nulla pariatur. Excepteur sint occaecat '.
      'cupidatat non proident, sunt in culpa qui officia deserunt '.
      'mollit anim id est laborum.</p>'.
      '<p class="plr pll plb">Lorem ipsum dolor sit amet, consectetur, '.
      'sed do eiusmod tempor incididunt ut labore et dolore magna '.
      'aliqua. Ut enim ad minim veniam, quis nostrud exercitation '.
      'ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis '.
      'aute irure dolor in reprehenderit in voluptate velit esse cillum '.
      'dolore eu fugiat nulla pariatur. Excepteur sint occaecat '.
      'cupidatat non proident, sunt in culpa qui officia deserunt '.
      'mollit anim id est laborum.</p>'.
      '<p class="plr pll plb">Lorem ipsum dolor sit amet, consectetur, '.
      'sed do eiusmod tempor incididunt ut labore et dolore magna '.
      'aliqua. Ut enim ad minim veniam, quis nostrud exercitation '.
      'ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis '.
      'aute irure dolor in reprehenderit in voluptate velit esse cillum '.
      'dolore eu fugiat nulla pariatur. Excepteur sint occaecat '.
      'cupidatat non proident, sunt in culpa qui officia deserunt '.
      'mollit anim id est laborum.</p>'.
      '<p class="plr pll plb">Lorem ipsum dolor sit amet, consectetur, '.
      'sed do eiusmod tempor incididunt ut labore et dolore magna '.
      'aliqua. Ut enim ad minim veniam, quis nostrud exercitation '.
      'ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis '.
      'aute irure dolor in reprehenderit in voluptate velit esse cillum '.
      'dolore eu fugiat nulla pariatur. Excepteur sint occaecat '.
      'cupidatat non proident, sunt in culpa qui officia deserunt '.
      'mollit anim id est laborum.</p>');

     $content = new PHUIDocumentView();
     $content->setBook(pht('Book or Project Name'), pht('Article'));
     $content->setHeader($header);
     $content->setFluid(true);
     $content->setTopNav($topnav);
     $content->setSidenav($sidenav);
     $content->appendChild($document);
     $content->setFontKit(PHUIDocumentView::FONT_SOURCE_SANS);

    return $content;
  }
}
