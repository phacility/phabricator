<?php

final class PHUIButtonBarExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Button Bar');
  }

  public function getDescription() {
    return pht('A minimal UI for Buttons');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    // Icon Buttons
    $icons = array(
      'Go Back' => 'chevron-left',
      'Choose Date' => 'calendar',
      'Edit View' => 'pencil',
      'Go Forward' => 'chevron-right');
    $button_bar = new PHUIButtonBarView();
    foreach ($icons as $text => $icon) {
      $image = id(new PHUIIconView())
          ->setSpriteSheet(PHUIIconView::SPRITE_BUTTONBAR)
          ->setSpriteIcon($icon);
      $button = id(new PHUIButtonView())
        ->setTag('a')
        ->setColor(PHUIButtonView::GREY)
        ->setTitle($text)
        ->setIcon($image);

      $button_bar->addButton($button);
    }

    $layout = id(new PHUIBoxView())
      ->appendChild($button_bar)
      ->addPadding(PHUI::PADDING_LARGE);

    $wrap1 = id(new PHUIObjectBoxView())
      ->setHeaderText('Button Bar Example')
      ->appendChild($layout);

    return array($wrap1);
  }
}
