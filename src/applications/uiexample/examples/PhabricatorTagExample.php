<?php

final class PhabricatorTagExample extends PhabricatorUIExample {

  public function getName() {
    return 'Tags';
  }

  public function getDescription() {
    return hsprintf('Use <tt>PhabricatorTagView</tt> to render various tags.');
  }

  public function renderExample() {
    $tags = array();

    $tags[] = 'Hey, ';
    $tags[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_PERSON)
      ->setName('@alincoln')
      ->setHref('#');
    $tags[] = ' how is stuff?';
    $tags[] = hsprintf('<br /><br />');


    $tags[] = 'Did you hear that ';
    $tags[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_PERSON)
      ->setName('@gwashington')
      ->setDotColor(PhabricatorTagView::COLOR_RED)
      ->setHref('#');
    $tags[] = ' is away, ';
    $tags[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_PERSON)
      ->setName('@tjefferson')
      ->setDotColor(PhabricatorTagView::COLOR_ORANGE)
      ->setHref('#');
    $tags[] = ' has some errands, and ';
    $tags[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_PERSON)
      ->setName('@rreagan')
      ->setDotColor(PhabricatorTagView::COLOR_GREY)
      ->setHref('#');
    $tags[] = ' is gone?';
    $tags[] = hsprintf('<br /><br />');

    $tags[] = 'Take a look at ';
    $tags[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_OBJECT)
      ->setName('D123')
      ->setHref('#');
    $tags[] = ' when you get a chance.';
    $tags[] = hsprintf('<br /><br />');

    $tags[] = 'Hmm? ';
    $tags[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_OBJECT)
      ->setName('D123')
      ->setClosed(true)
      ->setHref('#');
    $tags[] = ' is ';
    $tags[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setBackgroundColor(PhabricatorTagView::COLOR_BLACK)
      ->setName('Abandoned');
    $tags[] = '.';
    $tags[] = hsprintf('<br /><br />');

    $tags[] = 'I hope someone is going to ';
    $tags[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_OBJECT)
      ->setName('T123: Water The Dog')
      ->setBarColor(PhabricatorTagView::COLOR_REDORANGE)
      ->setHref('#');
    $tags[] = ' -- that task is ';
    $tags[] = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setBackgroundColor(PhabricatorTagView::COLOR_REDORANGE)
      ->setName('High Priority');
    $tags[] = '!';
    $tags[] = hsprintf('<br /><br />');


    $tags[] = id(new PhabricatorHeaderView())
      ->setHeader('Colors');

    $colors = PhabricatorTagView::getColors();
    foreach ($colors as $color) {
      $tags[] = id(new PhabricatorTagView())
        ->setType(PhabricatorTagView::TYPE_STATE)
        ->setBackgroundColor($color)
        ->setName(ucwords($color));
      $tags[] = hsprintf('<br /><br />');
    }

    $tags[] = id(new PhabricatorHeaderView())
      ->setHeader('Holidays?');

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
      ->setBackgroundColor(PhabricatorTagView::COLOR_MAGENTA)
      ->setDotColor(PhabricatorTagView::COLOR_YELLOW)
      ->setBarColor(PhabricatorTagView::COLOR_BLUE)
      ->setName('Easter');

    return phutil_tag(
      'div',
      array('class' => 'ml'),
      $tags);
  }
}
