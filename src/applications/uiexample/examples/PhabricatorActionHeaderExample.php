<?php

final class PhabricatorActionHeaderExample extends PhabricatorUIExample {

  public function getName() {
    return 'Action Headers';
  }

  public function getDescription() {
    return 'Various header layouts with and without icons';
  }

  public function renderExample() {

/* Colors */
    $title1 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Header Plain'));

    $header1 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Colorless');

    $header2 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Light Grey')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_GREY);

    $header3 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Blue')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_BLUE);

    $header4 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Green')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_GREEN);

    $header5 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Red')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_RED);

    $header6 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Yellow')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_YELLOW);

    $layout1 = id(new AphrontMultiColumnView())
      ->addColumn($header1)
      ->addColumn($header2)
      ->addColumn($header3)
      ->addColumn($header4)
      ->addColumn($header5)
      ->addColumn($header6)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_SMALL);

    $wrap1 = id(new PHUIBoxView())
      ->appendChild($layout1)
      ->addMargin(PHUI::MARGIN_LARGE);

/* Policy Icons */
    $title2 = id(new PhabricatorHeaderView())
      ->setHeader(pht('With Policy Icons'));

    $header1 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Company')
      ->setHeaderIcon('company-dark');

    $header2 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Public')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_GREY)
      ->setHeaderIcon('public-dark');

    $header3 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Restricted')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_BLUE)
      ->setHeaderIcon('restricted-white');

    $header4 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Company')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_GREEN)
      ->setHeaderIcon('company-white');

    $header5 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Public')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_RED)
      ->setHeaderIcon('public-white');

    $header6 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Restricted')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_YELLOW)
      ->setHeaderIcon('restriced-white');

    $layout2 = id(new AphrontMultiColumnView())
      ->addColumn($header1)
      ->addColumn($header2)
      ->addColumn($header3)
      ->addColumn($header4)
      ->addColumn($header5)
      ->addColumn($header6)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_SMALL);

    $wrap2 = id(new PHUIBoxView())
      ->appendChild($layout2)
      ->addMargin(PHUI::MARGIN_LARGE);


/* Action Icons */
    $title3 = id(new PhabricatorHeaderView())
      ->setHeader(pht('With Action Icons'));

    $action1 = new PHUIIconView();
    $action1->setSpriteSheet(PHUIIconView::SPRITE_ACTIONS);
    $action1->setSpriteIcon('settings-grey');
    $action1->setHref('#');

    $action2 = new PHUIIconView();
    $action2->setSpriteSheet(PHUIIconView::SPRITE_ACTIONS);
    $action2->setSpriteIcon('heart-white');
    $action2->setHref('#');

    $action3 = new PHUIIconView();
    $action3->setSpriteSheet(PHUIIconView::SPRITE_ACTIONS);
    $action3->setSpriteIcon('tag-white');
    $action3->setHref('#');

    $action4 = new PHUIIconView();
    $action4->setSpriteSheet(PHUIIconView::SPRITE_ACTIONS);
    $action4->setSpriteIcon('new-white');
    $action4->setHref('#');

    $action5 = new PHUIIconView();
    $action5->setSpriteSheet(PHUIIconView::SPRITE_ACTIONS);
    $action5->setSpriteIcon('search-white');
    $action5->setHref('#');

    $action6 = new PHUIIconView();
    $action6->setSpriteSheet(PHUIIconView::SPRITE_ACTIONS);
    $action6->setSpriteIcon('move-white');
    $action6->setHref('#');

    $header1 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Company')
      ->setHeaderHref('http://example.com/')
      ->addAction($action1);

    $header2 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Public')
      ->setHeaderHref('http://example.com/')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_GREY)
      ->addAction($action1);

    $header3 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Restricted')
      ->setHeaderHref('http://example.com/')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_BLUE)
      ->addAction($action2);

    $header4 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Company')
      ->setHeaderHref('http://example.com/')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_GREEN)
      ->addAction($action3);

    $header5 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Public')
      ->setHeaderHref('http://example.com/')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_RED)
      ->addAction($action4)
      ->addAction($action5);

    $header6 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Restricted')
      ->setHeaderHref('http://example.com/')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_YELLOW)
      ->addAction($action6);

    $layout3 = id(new AphrontMultiColumnView())
      ->addColumn($header1)
      ->addColumn($header2)
      ->addColumn($header3)
      ->addColumn($header4)
      ->addColumn($header5)
      ->addColumn($header6)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_SMALL);

    $wrap3 = id(new PHUIBoxView())
      ->appendChild($layout3)
      ->addMargin(PHUI::MARGIN_LARGE);

/* Action Icons */
    $title4 = id(new PhabricatorHeaderView())
      ->setHeader(pht('With Tags'));

    $tag1 = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setBackgroundColor(PhabricatorTagView::COLOR_RED)
      ->setName('Open');

    $tag2 = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setBackgroundColor(PhabricatorTagView::COLOR_BLUE)
      ->setName('Closed');

    $action1 = new PHUIIconView();
    $action1->setSpriteSheet(PHUIIconView::SPRITE_ACTIONS);
    $action1->setSpriteIcon('flag-grey');
    $action1->setHref('#');

    $header1 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Company')
      ->setTag($tag2);

    $header2 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Public')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_GREY)
      ->addAction($action1)
      ->setTag($tag1);

    $header3 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Restricted')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_BLUE)
      ->setTag($tag2);

    $header4 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Company')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_GREEN)
      ->setTag($tag1);

    $header5 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Public')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_RED)
      ->setTag($tag2);

    $header6 = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle('Restricted')
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_YELLOW)
      ->setTag($tag1);

    $layout4 = id(new AphrontMultiColumnView())
      ->addColumn($header1)
      ->addColumn($header2)
      ->addColumn($header3)
      ->addColumn($header4)
      ->addColumn($header5)
      ->addColumn($header6)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_SMALL);

    $wrap4 = id(new PHUIBoxView())
      ->appendChild($layout4)
      ->addMargin(PHUI::MARGIN_LARGE);

    return phutil_tag(
      'div',
        array(),
        array(
          $title1,
          $wrap1,
          $title2,
          $wrap2,
          $title3,
          $wrap3,
          $title4,
          $wrap4
        ));
  }
}
