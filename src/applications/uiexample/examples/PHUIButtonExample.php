<?php

final class PHUIButtonExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Buttons');
  }

  public function getDescription() {
    return pht(
      'Use %s to render buttons.',
      phutil_tag('tt', array(), '&lt;button&gt;'));
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

   // PHUIButtonView
   $colors = array(
     null,
     PHUIButtonView::GREEN,
     PHUIButtonView::RED,
     PHUIButtonView::GREY,
    );
   $sizes = array(null, PHUIButtonView::SMALL);
   $column = array();
   foreach ($colors as $color) {
     foreach ($sizes as $key => $size) {
       $column[$key][] = id(new PHUIButtonView())
        ->setColor($color)
        ->setSize($size)
        ->setTag('a')
        ->setText(pht('Clicky'));
      $column[$key][] = hsprintf('<br /><br />');
     }
   }
   foreach ($colors as $color) {
     $column[2][] = id(new PHUIButtonView())
        ->setColor($color)
        ->setTag('button')
        ->setText(pht('Button'))
        ->setDropdown(true);
      $column[2][] = hsprintf('<br /><br />');
   }

   $layout2 = id(new AphrontMultiColumnView())
      ->addColumn($column[0])
      ->addColumn($column[1])
      ->addColumn($column[2])
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_MEDIUM);

    // Icon Buttons

    $column = array();
    $icons = array(
      array(
        'text' => pht('Comment'),
        'icon' => 'fa-comment',
        'dropdown' => true,
      ),
      array(
        'text' => pht('Give Token'),
        'icon' => 'fa-trophy',
        'dropdown' => true,
      ),
      array(
        'text' => pht('Reverse Time'),
        'icon' => 'fa-clock-o',
      ),
      array(
        'text' => pht('Implode Earth'),
        'icon' => 'fa-exclamation-triangle',
      ),
      array(
        'icon' => 'fa-rocket',
        'dropdown' => true,
      ),
      array(
        'icon' => 'fa-clipboard',
        'dropdown' => true,
      ),
      array(
        'icon' => 'fa-upload',
        'disabled' => true,
      ),
      array(
        'icon' => 'fa-street-view',
        'selected' => true,
      ),
      array(
        'text' => pht('Copy "Quack" to Clipboard'),
        'icon' => 'fa-clipboard',
        'copy' => pht('Quack'),
      ),
    );
    foreach ($icons as $text => $spec) {
      $button = id(new PHUIButtonView())
        ->setTag('a')
        ->setColor(PHUIButtonView::GREY)
        ->setIcon(idx($spec, 'icon'))
        ->setText(idx($spec, 'text'))
        ->setSelected(idx($spec, 'selected'))
        ->setDisabled(idx($spec, 'disabled'))
        ->addClass(PHUI::MARGIN_SMALL_RIGHT)
        ->setDropdown(idx($spec, 'dropdown'));

      $copy = idx($spec, 'copy');
      if ($copy !== null) {
        Javelin::initBehavior('phabricator-clipboard-copy');

        $button->addClass('clipboard-copy');
        $button->addSigil('clipboard-copy');
        $button->setMetadata(
          array(
            'text' => $copy,
          ));
      }

      $column[] = $button;
    }

    $layout3 = id(new AphrontMultiColumnView())
      ->addColumn($column)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_MEDIUM);

    $icons = array(
      'Subscribe' => 'fa-check-circle bluegrey',
      'Edit' => 'fa-pencil bluegrey',
    );
    $designs = array(
      PHUIButtonView::BUTTONTYPE_SIMPLE,
    );
    $colors = array('', 'red', 'green', 'yellow');
    $column = array();
    foreach ($designs as $design) {
      foreach ($colors as $color) {
        foreach ($icons as $text => $icon) {
          $column[] = id(new PHUIButtonView())
            ->setTag('a')
            ->setButtonType($design)
            ->setColor($color)
            ->setIcon($icon)
            ->setText($text)
            ->addClass(PHUI::MARGIN_SMALL_RIGHT);
        }
      }
    }

    $layout4 = id(new AphrontMultiColumnView())
      ->addColumn($column)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_MEDIUM);


    // Baby Got Back Buttons

    $column = array();
    $icons = array('Asana', 'Github', 'Facebook', 'Google', 'LDAP');
    foreach ($icons as $icon) {
      $image = id(new PHUIIconView())
        ->setSpriteSheet(PHUIIconView::SPRITE_LOGIN)
        ->setSpriteIcon($icon);
      $column[] = id(new PHUIButtonView())
        ->setTag('a')
        ->setSize(PHUIButtonView::BIG)
        ->setColor(PHUIButtonView::GREY)
        ->setIcon($image)
        ->setText(pht('Log In or Register'))
        ->setSubtext($icon)
        ->addClass(PHUI::MARGIN_MEDIUM_RIGHT);
    }

    $layout5 = id(new AphrontMultiColumnView())
      ->addColumn($column)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_MEDIUM);


    // Set it and forget it

    $head2 = id(new PHUIHeaderView())
      ->setHeader('PHUIButtonView')
      ->addClass('ml');

    $head3 = id(new PHUIHeaderView())
      ->setHeader(pht('Icon Buttons'))
      ->addClass('ml');

    $head4 = id(new PHUIHeaderView())
      ->setHeader(pht('Simple Buttons'))
      ->addClass('ml');

    $head5 = id(new PHUIHeaderView())
      ->setHeader(pht('Big Icon Buttons'))
      ->addClass('ml');

    $wrap2 = id(new PHUIBoxView())
      ->appendChild($layout2)
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap3 = id(new PHUIBoxView())
      ->appendChild($layout3)
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap4 = id(new PHUIBoxView())
      ->appendChild($layout4)
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap5 = id(new PHUIBoxView())
      ->appendChild($layout5)
      ->addMargin(PHUI::MARGIN_LARGE);

    return array(
      $head2,
      $wrap2,
      $head3,
      $wrap3,
      $head4,
      $wrap4,
      $head5,
      $wrap5,
    );
  }
}
