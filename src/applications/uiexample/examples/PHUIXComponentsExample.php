<?php

final class PHUIXComponentsExample extends PhabricatorUIExample {

  public function getName() {
    return pht('PHUIX Components');
  }

  public function getDescription() {
    return pht('Copy/paste to make design maintenance twice as difficult.');
  }

  public function getCategory() {
    return pht('PHUIX');
  }

  public function renderExample() {
    $content = array();

    $icons = array(
      array(
        'icon' => 'fa-rocket',
      ),
      array(
        'icon' => 'fa-cloud',
        'color' => 'indigo',
      ),
    );

    foreach ($icons as $spec) {
      $icon = new PHUIIconView();

      $icon->setIcon(idx($spec, 'icon'), idx($spec, 'color'));

      $client_id = celerity_generate_unique_node_id();

      $server_view = $icon;
      $client_view = javelin_tag(
        'div',
        array(
          'id' => $client_id,
        ));

      Javelin::initBehavior(
        'phuix-example',
        array(
          'type' => 'icon',
          'id' => $client_id,
          'spec' => $spec,
        ));

      $content[] = id(new AphrontMultiColumnView())
        ->addColumn($server_view)
        ->addColumn($client_view);
    }


    $buttons = array(
      array(
        'text' => pht('Submit'),
      ),
      array(
        'text' => pht('Activate'),
        'icon' => 'fa-rocket',
      ),
      array(
        'type' => PHUIButtonView::BUTTONTYPE_SIMPLE,
        'text' => pht('3 / 5 Comments'),
        'icon' => 'fa-comment',
      ),
      array(
        'color' => PHUIButtonView::GREEN,
        'text' => pht('Environmental!'),
      ),
      array(
        'icon' => 'fa-cog',
      ),
      array(
        'icon' => 'fa-cog',
        'type' => PHUIButtonView::BUTTONTYPE_SIMPLE,
      ),
      array(
        'text' => array('2 + 2', ' ', '=', ' ', '4'),
      ),
      array(
        'color' => PHUIButtonView::GREY,
        'text' => pht('Cancel'),
      ),
      array(
        'text' => array('<strong />'),
      ),
    );

    foreach ($buttons as $spec) {
      $button = new PHUIButtonView();

      if (idx($spec, 'text') !== null) {
        $button->setText($spec['text']);
      }

      if (idx($spec, 'icon') !== null) {
        $button->setIcon($spec['icon']);
      }

      if (idx($spec, 'type') !== null) {
        $button->setButtonType($spec['type']);
      }

      if (idx($spec, 'color') !== null) {
        $button->setColor($spec['color']);
      }

      $client_id = celerity_generate_unique_node_id();

      $server_view = $button;
      $client_view = javelin_tag(
        'div',
        array(
          'id' => $client_id,
        ));

      Javelin::initBehavior(
        'phuix-example',
        array(
          'type' => 'button',
          'id' => $client_id,
          'spec' => $spec,
        ));

      $content[] = id(new AphrontMultiColumnView())
        ->addColumn($server_view)
        ->addColumn($client_view);
    }

    return id(new PHUIBoxView())
      ->appendChild($content)
      ->addMargin(PHUI::MARGIN_LARGE);
  }
}
