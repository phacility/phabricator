<?php

final class PHUIDiffRevealIconView extends AphrontView {

  public function render() {
    $icon = id(new PHUIIconView())
      ->setIcon('fa-comment')
      ->addSigil('has-tooltip')
      ->setMetadata(
        array(
          'tip' => pht('Show Hidden Comments'),
          'align' => 'E',
          'size' => 275,
        ));

    return javelin_tag(
      'a',
      array(
        'href' => '#',
        'class' => 'reveal-inlines',
        'sigil' => 'reveal-inlines',
        'mustcapture' => true,
      ),
      $icon);
  }

}
