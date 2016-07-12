<?php

abstract class PhabricatorLipsumArtist extends Phobject {

  protected function getHSBColor($h, $s, $b) {
    if ($s == 0) {
      $cr = $b;
      $cg = $b;
      $cb = $b;
    } else {
      $h /= 60;
      $i = (int)$h;
      $f = $h - $i;
      $p = $b * (1 - $s);
      $q = $b * (1 - $s * $f);
      $t = $b * (1 - $s * (1 - $f));
      switch ($i) {
        case 0:
          $cr = $b;
          $cg = $t;
          $cb = $p;
          break;
        case 1:
          $cr = $q;
          $cg = $b;
          $cb = $p;
          break;
        case 2:
          $cr = $p;
          $cg = $b;
          $cb = $t;
          break;
        case 3:
          $cr = $p;
          $cg = $q;
          $cb = $b;
          break;
        case 4:
          $cr = $t;
          $cg = $p;
          $cb = $b;
          break;
        default:
          $cr = $b;
          $cg = $p;
          $cb = $q;
          break;
      }
    }

    $cr = (int)round($cr * 255);
    $cg = (int)round($cg * 255);
    $cb = (int)round($cb * 255);

    return ($cr << 16) + ($cg << 8) + $cb;
  }

  public function generate($x, $y) {
    $image = imagecreatetruecolor($x, $y);
    $this->draw($image, $x, $y);
    return PhabricatorImageTransformer::saveImageDataInAnyFormat(
      $image,
      'image/jpeg');
  }

  abstract protected function draw($image, $x, $y);

}
