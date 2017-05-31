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

    $colors = array('', 'green', 'grey', 'disabled');
    $sizes = array('', 'small');
    $tags = array('a', 'button');

    // phutil_tag

    $column = array();
    foreach ($tags as $tag) {
      foreach ($colors as $color) {
        foreach ($sizes as $key => $size) {
          $class = implode(' ', array($color, $size));

          if ($tag == 'a') {
            $class .= ' button';
          }

          $column[$key][] = phutil_tag(
            $tag,
            array(
              'class' => $class,
            ),
            phutil_utf8_ucwords($size.' '.$color.' '.$tag));

          $column[$key][] = hsprintf('<br /><br />');
        }
      }
    }

    $column3 = array();
    foreach ($colors as $color) {
      $caret = phutil_tag('span', array('class' => 'caret'), '');
      $column3[] = phutil_tag(
          'a',
            array(
              'class' => $color.' button dropdown',
            ),
          array(
            phutil_utf8_ucwords($color.' Dropdown'),
            $caret,
          ));
        $column3[] = hsprintf('<br /><br />');
    }

    $layout1 = id(new AphrontMultiColumnView())
      ->addColumn($column[0])
      ->addColumn($column[1])
      ->addColumn($column3)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_MEDIUM);

   // PHUIButtonView

   $colors = array(
     null,
     PHUIButtonView::GREEN,
     PHUIButtonView::GREY,
     PHUIButtonView::DISABLED,
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
      ),
      array(
        'text' => pht('Give Token'),
        'icon' => 'fa-trophy',
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
      ),
      array(
        'icon' => 'fa-clipboard',
      ),
      array(
        'icon' => 'fa-upload',
      ),
      array(
        'icon' => 'fa-street-view',
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
        ->addClass(PHUI::MARGIN_SMALL_RIGHT);

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
    $column = array();
    foreach ($designs as $design) {
      foreach ($icons as $text => $icon) {
        $column[] = id(new PHUIButtonView())
          ->setTag('a')
          ->setButtonType($design)
          ->setIcon($icon)
          ->setText($text)
          ->addClass(PHUI::MARGIN_SMALL_RIGHT);
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
        ->setText(pht('Login or Register'))
        ->setSubtext($icon)
        ->addClass(PHUI::MARGIN_MEDIUM_RIGHT);
    }

    $layout5 = id(new AphrontMultiColumnView())
      ->addColumn($column)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_MEDIUM);


    // Set it and forget it

    $head1 = id(new PHUIHeaderView())
      ->setHeader('phutil_tag');

    $head2 = id(new PHUIHeaderView())
      ->setHeader('PHUIButtonView');

    $head3 = id(new PHUIHeaderView())
      ->setHeader(pht('Icon Buttons'));

    $head4 = id(new PHUIHeaderView())
      ->setHeader(pht('Simple Buttons'));

    $head5 = id(new PHUIHeaderView())
      ->setHeader(pht('Big Icon Buttons'));

    $wrap1 = id(new PHUIBoxView())
      ->appendChild($layout1)
      ->addMargin(PHUI::MARGIN_LARGE);

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
      $head1,
      $wrap1,
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
