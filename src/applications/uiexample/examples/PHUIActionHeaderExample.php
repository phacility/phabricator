<?php

final class PHUIActionHeaderExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Action Headers');
  }

  public function getDescription() {
    return pht('Various header layouts with and without icons');
  }

  public function renderExample() {

/* Colors */
    $title1 = id(new PHUIHeaderView())
      ->setHeader(pht('Header Plain'));

    $header1 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Colorless'));

    $header2 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Light Grey'))
      ->setHeaderColor(PHUIActionHeaderView::HEADER_GREY);

    $header3 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Light Blue'))
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTBLUE);

    $header4 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Light Green'))
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTGREEN);

    $header5 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Light Red'))
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTRED);

    $header6 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Light Violet'))
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTVIOLET);

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

    // Policy Icons
    $title2 = id(new PHUIHeaderView())
      ->setHeader(pht('With Icons'));

    $header1 = id(new PHUIActionHeaderView())
      ->setHeaderTitle('Quack')
      ->setHeaderIcon(
        id(new PHUIIconView())
          ->setIconFont('fa-coffee'));

    $header2 = id(new PHUIActionHeaderView())
      ->setHeaderTitle('Moo')
      ->setHeaderColor(PHUIActionHeaderView::HEADER_GREY)
      ->setHeaderIcon(
        id(new PHUIIconView())
          ->setIconFont('fa-magic'));

    $header3 = id(new PHUIActionHeaderView())
      ->setHeaderTitle('Woof')
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTBLUE)
      ->setHeaderIcon(
        id(new PHUIIconView())
          ->setIconFont('fa-fighter-jet'));

    $header4 = id(new PHUIActionHeaderView())
      ->setHeaderTitle('Buzz')
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTGREEN)
      ->setHeaderIcon(
        id(new PHUIIconView())
          ->setIconFont('fa-child'));

    $header5 = id(new PHUIActionHeaderView())
      ->setHeaderTitle('Fizz')
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTRED)
      ->setHeaderIcon(
        id(new PHUIIconView())
          ->setIconFont('fa-car'));

    $header6 = id(new PHUIActionHeaderView())
      ->setHeaderTitle('Blarp')
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTVIOLET)
      ->setHeaderIcon(
        id(new PHUIIconView())
          ->setIconFont('fa-truck'));

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


    // Action Icons
    $title3 = id(new PHUIHeaderView())
      ->setHeader(pht('With Action Icons'));

    $action1 = new PHUIIconView();
    $action1->setIconFont('fa-cog');
    $action1->setHref('#');

    $action2 = new PHUIIconView();
    $action2->setIconFont('fa-heart');
    $action2->setHref('#');

    $action3 = new PHUIIconView();
    $action3->setIconFont('fa-tag');
    $action3->setHref('#');

    $action4 = new PHUIIconView();
    $action4->setIconFont('fa-plus');
    $action4->setHref('#');

    $action5 = new PHUIIconView();
    $action5->setIconFont('fa-search');
    $action5->setHref('#');

    $action6 = new PHUIIconView();
    $action6->setIconFont('fa-arrows');
    $action6->setHref('#');

    $header1 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Company'))
      ->setHeaderHref('http://example.com/')
      ->addAction($action1);

    $header2 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Public'))
      ->setHeaderHref('http://example.com/')
      ->setHeaderColor(PHUIActionHeaderView::HEADER_GREY)
      ->addAction($action1);

    $header3 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Restricted'))
      ->setHeaderHref('http://example.com/')
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTBLUE)
      ->addAction($action2);

    $header4 = id(new PHUIActionHeaderView())
      ->setHeaderTitle('Company')
      ->setHeaderHref('http://example.com/')
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTGREEN)
      ->addAction($action3);

    $header5 = id(new PHUIActionHeaderView())
      ->setHeaderTitle('Public')
      ->setHeaderHref('http://example.com/')
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTRED)
      ->addAction($action4)
      ->addAction($action5);

    $header6 = id(new PHUIActionHeaderView())
      ->setHeaderTitle('Restricted')
      ->setHeaderHref('http://example.com/')
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTVIOLET)
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

    // Action Icons
    $title4 = id(new PHUIHeaderView())
      ->setHeader(pht('With Tags'));

    $tag1 = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_STATE)
      ->setBackgroundColor(PHUITagView::COLOR_RED)
      ->setName(pht('Open'));

    $tag2 = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_STATE)
      ->setBackgroundColor(PHUITagView::COLOR_BLUE)
      ->setName(pht('Closed'));

    $action1 = new PHUIIconView();
    $action1->setIconFont('fa-flag');
    $action1->setHref('#');

    $header1 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Company'))
      ->setTag($tag2);

    $header2 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Public'))
      ->setHeaderColor(PHUIActionHeaderView::HEADER_GREY)
      ->addAction($action1)
      ->setTag($tag1);

    $header3 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Restricted'))
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTBLUE)
      ->setTag($tag2);

    $header4 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Company'))
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTGREEN)
      ->setTag($tag1);

    $header5 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Public'))
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTRED)
      ->setTag($tag2);

    $header6 = id(new PHUIActionHeaderView())
      ->setHeaderTitle(pht('Restricted'))
      ->setHeaderColor(PHUIActionHeaderView::HEADER_LIGHTVIOLET)
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
          $wrap4,
        ));
  }
}
