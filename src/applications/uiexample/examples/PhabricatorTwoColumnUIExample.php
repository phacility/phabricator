<?php

final class PhabricatorTwoColumnUIExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Two Column Layout');
  }

  public function getDescription() {
    return pht('Two Column mobile friendly layout');
  }

  public function renderExample() {

    $main = phutil_tag(
      'div',
      array(
        'style' => 'border: 1px solid blue; padding: 20px;',
      ),
      'Mary, mary quite contrary.');

    $side = phutil_tag(
      'div',
      array(
        'style' => 'border: 1px solid red; padding: 20px;',
      ),
      'How does your garden grow?');


    $content = id(new AphrontTwoColumnView())
      ->setMainColumn($main)
      ->setSideColumn($side);

    return $content;
  }
}
