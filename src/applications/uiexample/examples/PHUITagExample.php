<?php

final class PHUITagExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Tags');
  }

  public function getDescription() {
    return pht(
      'Use %s to render various tags.',
      phutil_tag('tt', array(), 'PHUITagView'));
  }

  public function renderExample() {
    $intro = array();

    $intro[] = 'Hey, ';
    $intro[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_PERSON)
      ->setName('@alincoln')
      ->setHref('#');
    $intro[] = ' how is stuff?';
    $intro[] = hsprintf('<br /><br />');


    $intro[] = 'Did you hear that ';
    $intro[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_PERSON)
      ->setName('@gwashington')
      ->setDotColor(PHUITagView::COLOR_RED)
      ->setHref('#');
    $intro[] = ' is away, ';
    $intro[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_PERSON)
      ->setName('@tjefferson')
      ->setDotColor(PHUITagView::COLOR_ORANGE)
      ->setHref('#');
    $intro[] = ' has some errands, and ';
    $intro[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_PERSON)
      ->setName('@rreagan')
      ->setDotColor(PHUITagView::COLOR_GREY)
      ->setHref('#');
    $intro[] = ' is gone?';
    $intro[] = hsprintf('<br /><br />');

    $intro[] = 'Take a look at ';
    $intro[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_OBJECT)
      ->setName('D123')
      ->setHref('#');
    $intro[] = ' when you get a chance.';
    $intro[] = hsprintf('<br /><br />');

    $intro[] = 'Hmm? ';
    $intro[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_OBJECT)
      ->setName('D123')
      ->setClosed(true)
      ->setHref('#');
    $intro[] = ' is ';
    $intro[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_STATE)
      ->setBackgroundColor(PHUITagView::COLOR_BLACK)
      ->setName('Abandoned');
    $intro[] = '.';
    $intro[] = hsprintf('<br /><br />');

    $intro[] = 'I hope someone is going to ';
    $intro[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_OBJECT)
      ->setName('T123: Water The Dog')
      ->setHref('#');
    $intro[] = ' -- that task is ';
    $intro[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_STATE)
      ->setBackgroundColor(PHUITagView::COLOR_RED)
      ->setName('High Priority');
    $intro[] = '!';

    $intro = id(new PHUIBoxView())
      ->appendChild($intro)
      ->addPadding(PHUI::PADDING_LARGE);

    $header1 = id(new PHUIHeaderView())
      ->setHeader('Colors');

    $colors = PHUITagView::getColors();
    $tags = array();
    foreach ($colors as $color) {
      $tags[] = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_STATE)
        ->setBackgroundColor($color)
        ->setName(ucwords($color));
      $tags[] = hsprintf('<br /><br />');
    }

    $content1 = id(new PHUIBoxView())
      ->appendChild($tags)
      ->addPadding(PHUI::PADDING_LARGE);

    $tags = array();
    $tags[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_STATE)
      ->setBackgroundColor(PHUITagView::COLOR_GREEN)
      ->setDotColor(PHUITagView::COLOR_RED)
      ->setName(pht('Christmas'));
    $tags[] = hsprintf('<br /><br />');
    $tags[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_OBJECT)
      ->setBackgroundColor(PHUITagView::COLOR_ORANGE)
      ->setDotColor(PHUITagView::COLOR_BLACK)
      ->setName(pht('Halloween'));
    $tags[] = hsprintf('<br /><br />');
    $tags[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_STATE)
      ->setBackgroundColor(PHUITagView::COLOR_INDIGO)
      ->setDotColor(PHUITagView::COLOR_YELLOW)
      ->setName(pht('Easter'));

    $content2 = id(new PHUIBoxView())
      ->appendChild($tags)
      ->addPadding(PHUI::PADDING_LARGE);

    $icons = array();
    $icons[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_STATE)
      ->setBackgroundColor(PHUITagView::COLOR_GREEN)
      ->setIcon('fa-check white')
      ->setName(pht('Passed'));
    $icons[] = hsprintf('<br /><br />');
    $icons[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_STATE)
      ->setBackgroundColor(PHUITagView::COLOR_RED)
      ->setIcon('fa-times white')
      ->setName(pht('Failed'));
    $icons[] = hsprintf('<br /><br />');
    $icons[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_STATE)
      ->setBackgroundColor(PHUITagView::COLOR_BLUE)
      ->setIcon('fa-refresh white')
      ->setName(pht('Running'));
    $icons[] = hsprintf('<br /><br />');
    $icons[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_STATE)
      ->setBackgroundColor(PHUITagView::COLOR_GREY)
      ->setIcon('fa-pause white')
      ->setName(pht('Paused'));
    $icons[] = hsprintf('<br /><br />');
    $icons[] = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_STATE)
      ->setBackgroundColor(PHUITagView::COLOR_BLACK)
      ->setIcon('fa-stop white')
      ->setName(pht('Stopped'));

    $content3 = id(new PHUIBoxView())
      ->appendChild($icons)
      ->addPadding(PHUI::PADDING_LARGE);

    $shades = PHUITagView::getShades();
    $tags = array();
    foreach ($shades as $shade) {
      $tags[] = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_OBJECT)
        ->setShade($shade)
        ->setIcon('fa-tags')
        ->setName(ucwords($shade))
        ->setHref('#');
      $tags[] = hsprintf('&nbsp;');
      $tags[] = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_OBJECT)
        ->setShade($shade)
        ->setSlimShady(true)
        ->setIcon('fa-tags')
        ->setName(ucwords($shade))
        ->setHref('#');
      $tags[] = hsprintf('<br /><br />');
    }

    $content4 = id(new PHUIBoxView())
      ->appendChild($tags)
      ->addPadding(PHUI::PADDING_LARGE);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Inline'))
      ->appendChild($intro);

    $box1 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Colors'))
      ->appendChild($content1);

    $box2 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Holidays'))
      ->appendChild($content2);

    $box3 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Icons'))
      ->appendChild($content3);

    $box4 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Shades'))
      ->appendChild($content4);

    return array($box, $box1, $box2, $box3, $box4);
  }
}
