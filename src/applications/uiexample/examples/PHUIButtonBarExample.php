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
      'Go Back' => 'fa-chevron-left bluegrey',
      'Choose Date' => 'fa-calendar bluegrey',
      'Edit View' => 'fa-pencil bluegrey',
      'Go Forward' => 'fa-chevron-right bluegrey');
    $button_bar = new PHUIButtonBarView();
    foreach ($icons as $text => $icon) {
      $image = id(new PHUIIconView())
          ->setIconFont($icon);
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
