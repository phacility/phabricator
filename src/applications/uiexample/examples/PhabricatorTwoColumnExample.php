<?php

final class PhabricatorTwoColumnExample extends PhabricatorUIExample {

  public function getName() {
    return 'Two Column Layout';
  }

  public function getDescription() {
    return 'Two Column mobile friendly layout';
  }

  public function renderExample() {

    $main = phutil_tag(
      'div',
      array(
        'style' => 'border: 1px solid blue; padding: 20px;'
      ),
      'Mary, mary quite contrary.');

    $side = phutil_tag(
      'div',
      array(
        'style' => 'border: 1px solid red; padding: 20px;'
      ),
      'How does your garden grow?');


    $content = id(new AphrontTwoColumnView)
          ->setMainColumn($main)
          ->setSideColumn($side);

    return $content;
  }
}
