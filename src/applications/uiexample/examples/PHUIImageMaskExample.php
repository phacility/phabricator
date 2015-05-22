<?php

final class PHUIImageMaskExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Image Masks');
  }

  public function getDescription() {
    return pht('Display images with crops.');
  }

  public function renderExample() {
    $image = celerity_get_resource_uri('/rsrc/image/examples/hero.png');
    $display_height = 100;
    $display_width = 200;

    $mask1 = id(new PHUIImageMaskView())
      ->addClass('ml')
      ->setImage($image)
      ->setDisplayHeight($display_height)
      ->setDisplayWidth($display_width)
      ->centerViewOnPoint(265, 185, 30, 140);

    $mask2 = id(new PHUIImageMaskView())
      ->addClass('ml')
      ->setImage($image)
      ->setDisplayHeight($display_height)
      ->setDisplayWidth($display_width)
      ->centerViewOnPoint(18, 18, 40, 80);

    $mask3 = id(new PHUIImageMaskView())
      ->addClass('ml')
      ->setImage($image)
      ->setDisplayHeight($display_height)
      ->setDisplayWidth($display_width)
      ->centerViewOnPoint(265, 185, 30, 140)
      ->withMask(true);

    $mask4 = id(new PHUIImageMaskView())
      ->addClass('ml')
      ->setImage($image)
      ->setDisplayHeight($display_height)
      ->setDisplayWidth($display_width)
      ->centerViewOnPoint(18, 18, 40, 80)
      ->withMask(true);

    $mask5 = id(new PHUIImageMaskView())
      ->addClass('ml')
      ->setImage($image)
      ->setDisplayHeight($display_height)
      ->setDisplayWidth($display_width)
      ->centerViewOnPoint(254, 272, 60, 240)
      ->withMask(true);

    $box1 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Center is in the middle'))
      ->appendChild($mask1);

    $box2 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Center is on an edge'))
      ->appendChild($mask2);

    $box3 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Center Masked'))
      ->appendChild($mask3);

    $box4 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Edge Masked'))
      ->appendChild($mask4);

    $box5 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Wide Masked'))
      ->appendChild($mask5);

    return phutil_tag(
      'div',
        array(),
        array(
          $box1,
          $box2,
          $box3,
          $box4,
          $box5,
        ));
  }
}
