<?php

final class PhabricatorTagExample extends PhabricatorUIExample {

  public function getName() {
    return 'Tags';
  }

  public function getDescription() {
    return hsprintf('Use <tt>PhabricatorTagView</tt> to render various tags.');
  }

  public function renderExample() {
    $intro = array();

    $intro[] = 'Hey, ';
    $intro[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_PERSON)
      ->setName('@alincoln')
      ->setHref('#');
    $intro[] = ' how is stuff?';
    $intro[] = hsprintf('<br /><br />');


    $intro[] = 'Did you hear that ';
    $intro[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_PERSON)
      ->setName('@gwashington')
      ->setDotColor(PhabricatorTagView::COLOR_RED)
      ->setHref('#');
    $intro[] = ' is away, ';
    $intro[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_PERSON)
      ->setName('@tjefferson')
      ->setDotColor(PhabricatorTagView::COLOR_ORANGE)
      ->setHref('#');
    $intro[] = ' has some errands, and ';
    $intro[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_PERSON)
      ->setName('@rreagan')
      ->setDotColor(PhabricatorTagView::COLOR_GREY)
      ->setHref('#');
    $intro[] = ' is gone?';
    $intro[] = hsprintf('<br /><br />');

    $intro[] = 'Take a look at ';
    $intro[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_OBJECT)
      ->setName('D123')
      ->setHref('#');
    $intro[] = ' when you get a chance.';
    $intro[] = hsprintf('<br /><br />');

    $intro[] = 'Hmm? ';
    $intro[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_OBJECT)
      ->setName('D123')
      ->setClosed(true)
      ->setHref('#');
    $intro[] = ' is ';
    $intro[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setBackgroundColor(PhabricatorTagView::COLOR_BLACK)
      ->setName('Abandoned');
    $intro[] = '.';
    $intro[] = hsprintf('<br /><br />');

    $intro[] = 'I hope someone is going to ';
    $intro[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_OBJECT)
      ->setName('T123: Water The Dog')
      ->setBarColor(PhabricatorTagView::COLOR_RED)
      ->setHref('#');
    $intro[] = ' -- that task is ';
    $intro[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setBackgroundColor(PhabricatorTagView::COLOR_RED)
      ->setName('High Priority');
    $intro[] = '!';

    $intro = id(new PHUIBoxView())
      ->appendChild($intro)
      ->setShadow(true)
      ->addPadding(PHUI::PADDING_LARGE)
      ->addMargin(PHUI::MARGIN_LARGE);

    $header1 = id(new PhabricatorHeaderView())
      ->setHeader('Colors');

    $colors = PhabricatorTagView::getColors();
    $tags = array();
    foreach ($colors as $color) {
      $tags[] = id(new PhabricatorTagView())
        ->setType(PhabricatorTagView::TYPE_STATE)
        ->setBackgroundColor($color)
        ->setName(ucwords($color));
      $tags[] = hsprintf('<br /><br />');
    }

    $content1 = id(new PHUIBoxView())
      ->appendChild($tags)
      ->setShadow(true)
      ->addPadding(PHUI::PADDING_LARGE)
      ->addMargin(PHUI::MARGIN_LARGE);

    $header2 = id(new PhabricatorHeaderView())
      ->setHeader('Holidays?');


    $tags = array();
    $tags[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setBackgroundColor(PhabricatorTagView::COLOR_GREEN)
      ->setDotColor(PhabricatorTagView::COLOR_RED)
      ->setBarColor(PhabricatorTagView::COLOR_RED)
      ->setName('Christmas');
    $tags[] = hsprintf('<br /><br />');
    $tags[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_OBJECT)
      ->setBackgroundColor(PhabricatorTagView::COLOR_ORANGE)
      ->setDotColor(PhabricatorTagView::COLOR_BLACK)
      ->setBarColor(PhabricatorTagView::COLOR_BLACK)
      ->setName('Halloween');
    $tags[] = hsprintf('<br /><br />');
    $tags[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setBackgroundColor(PhabricatorTagView::COLOR_INDIGO)
      ->setDotColor(PhabricatorTagView::COLOR_YELLOW)
      ->setBarColor(PhabricatorTagView::COLOR_BLUE)
      ->setName('Easter');

    $content2 = id(new PHUIBoxView())
      ->appendChild($tags)
      ->setShadow(true)
      ->addPadding(PHUI::PADDING_LARGE)
      ->addMargin(PHUI::MARGIN_LARGE);

    return array($intro, $header1, $content1, $header2, $content2);
  }
}
