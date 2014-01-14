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
      ->addPadding(PHUI::PADDING_LARGE);

    $header1 = id(new PHUIHeaderView())
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
      ->addPadding(PHUI::PADDING_LARGE);

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
      ->addPadding(PHUI::PADDING_LARGE);

    $icons = array();
    $icons[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setBackgroundColor(PhabricatorTagView::COLOR_GREEN)
      ->setIcon('check-white')
      ->setName('Passed');
    $icons[] = hsprintf('<br /><br />');
    $icons[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setBackgroundColor(PhabricatorTagView::COLOR_RED)
      ->setIcon('delete-white')
      ->setName('Failed');
    $icons[] = hsprintf('<br /><br />');
    $icons[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setBackgroundColor(PhabricatorTagView::COLOR_BLUE)
      ->setIcon('refresh-white')
      ->setName('Running');
    $icons[] = hsprintf('<br /><br />');
    $icons[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setBackgroundColor(PhabricatorTagView::COLOR_GREY)
      ->setIcon('pause-white')
      ->setName('Paused');
    $icons[] = hsprintf('<br /><br />');
    $icons[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setBackgroundColor(PhabricatorTagView::COLOR_BLACK)
      ->setIcon('stop-white')
      ->setName('Stopped');

    $content3 = id(new PHUIBoxView())
      ->appendChild($icons)
      ->addPadding(PHUI::PADDING_LARGE);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Inline')
      ->appendChild($intro);

    $box1 = id(new PHUIObjectBoxView())
      ->setHeaderText('Colors')
      ->appendChild($content1);

    $box2 = id(new PHUIObjectBoxView())
      ->setHeaderText('Holidays')
      ->appendChild($content2);

    $box3 = id(new PHUIObjectBoxView())
      ->setHeaderText('Icons')
      ->appendChild($content3);

    return array($box, $box1, $box2, $box3);
  }
}
