<?php

final class PhabricatorLipsumMondrianArtist extends PhabricatorLipsumArtist {

  protected function draw($image, $x, $y) {
    $c_white = 0xFFFFFF;
    $c_black = 0x000000;
    imagefill($image, 0, 0, $c_white);

    $lines_h = mt_rand(2, 5);
    $lines_v = mt_rand(2, 5);

    for ($ii = 0; $ii < $lines_h; $ii++) {
      $yp = mt_rand(0, $y);

      $thickness = mt_rand(2, 3);
      for ($jj = 0; $jj < $thickness; $jj++) {
        imageline($image, 0, $yp + $jj, $x, $yp + $jj, $c_black);
      }
    }

    for ($ii = 0; $ii < $lines_v; $ii++) {
      $xp = mt_rand(0, $x);

      $thickness = mt_rand(2, 3);
      for ($jj = 0; $jj < $thickness; $jj++) {
        imageline($image, $xp + $jj, 0, $xp + $jj, $y, $c_black);
      }
    }

    $fills = mt_rand(3, 8);
    for ($ii = 0; $ii < $fills; $ii++) {
      $xp = mt_rand(0, $x - 1);
      $yp = mt_rand(0, $y - 1);
      if (imagecolorat($image, $xp, $yp) != $c_white) {
        continue;
      }

      $c_fill = $this->getHSBColor(
        mt_rand(0, 359),
        mt_rand(80, 100) / 100,
        mt_rand(90, 100) / 100);

      imagefill($image, $xp, $yp, $c_fill);
    }
  }

}
