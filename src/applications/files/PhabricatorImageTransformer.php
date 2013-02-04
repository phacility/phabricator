<?php

final class PhabricatorImageTransformer {

  public function executeMemeTransform(
    PhabricatorFile $file,
    $upper_text,
    $lower_text) {
    $image = $this->applyMemeTo($file, $upper_text, $lower_text);
    return PhabricatorFile::newFromFileData(
      $image,
      array(
        'name' => 'meme-'.$file->getName(),
      ));
  }

  public function executeThumbTransform(
    PhabricatorFile $file,
    $x,
    $y) {

    $image = $this->crudelyScaleTo($file, $x, $y);

    return PhabricatorFile::newFromFileData(
      $image,
      array(
        'name' => 'thumb-'.$file->getName(),
      ));
  }

  public function executeProfileTransform(
    PhabricatorFile $file,
    $x,
    $min_y,
    $max_y) {

    $image = $this->crudelyCropTo($file, $x, $min_y, $max_y);

    return PhabricatorFile::newFromFileData(
      $image,
      array(
        'name' => 'profile-'.$file->getName(),
      ));
  }

  public function executePreviewTransform(
    PhabricatorFile $file,
    $size) {

    $image = $this->generatePreview($file, $size);

    return PhabricatorFile::newFromFileData(
      $image,
      array(
        'name' => 'preview-'.$file->getName(),
      ));
  }


  private function crudelyCropTo(PhabricatorFile $file, $x, $min_y, $max_y) {
    $data = $file->loadFileData();
    $img = imagecreatefromstring($data);
    $sx = imagesx($img);
    $sy = imagesy($img);

    $scaled_y = ($x / $sx) * $sy;
    if ($scaled_y > $max_y) {
      // This image is very tall and thin.
      $scaled_y = $max_y;
    } else if ($scaled_y < $min_y) {
      // This image is very short and wide.
      $scaled_y = $min_y;
    }

    $img = $this->applyScaleTo(
      $img,
      $x,
      $scaled_y);

    return $this->saveImageDataInAnyFormat($img, $file->getMimeType());
  }

  /**
   * Very crudely scale an image up or down to an exact size.
   */
  private function crudelyScaleTo(PhabricatorFile $file, $dx, $dy) {
    $data = $file->loadFileData();
    $src = imagecreatefromstring($data);

    $dst = $this->applyScaleTo($src, $dx, $dy);

    return $this->saveImageDataInAnyFormat($dst, $file->getMimeType());
  }

  private function applyScaleTo($src, $dx, $dy) {
    $x = imagesx($src);
    $y = imagesy($src);

    $scale = min(($dx / $x), ($dy / $y), 1);

    $dst = imagecreatetruecolor($dx, $dy);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));

    $sdx = $scale * $x;
    $sdy = $scale * $y;

    imagecopyresampled(
      $dst,
      $src,
      ($dx - $sdx) / 2,  ($dy - $sdy) / 2,
      0, 0,
      $sdx, $sdy,
      $x, $y);

    return $dst;
  }

  public static function getPreviewDimensions(PhabricatorFile $file, $size) {
    $data = $file->loadFileData();
    $src = imagecreatefromstring($data);

    $x = imagesx($src);
    $y = imagesy($src);

    $scale = min($size / $x, $size / $y, 1);

    $dx = max($size / 4, $scale * $x);
    $dy = max($size / 4, $scale * $y);

    $sdx = $scale * $x;
    $sdy = $scale * $y;

    return array(
      'x' => $x,
      'y' => $y,
      'dx' => $dx,
      'dy' => $dy,
      'sdx' => $sdx,
      'sdy' => $sdy
    );
  }

  private function generatePreview(PhabricatorFile $file, $size) {
    $data = $file->loadFileData();
    $src = imagecreatefromstring($data);

    $dimensions = self::getPreviewDimensions($file, $size);
    $x = $dimensions['x'];
    $y = $dimensions['y'];
    $dx = $dimensions['dx'];
    $dy = $dimensions['dy'];
    $sdx = $dimensions['sdx'];
    $sdy = $dimensions['sdy'];

    $dst = imagecreatetruecolor($dx, $dy);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));

    imagecopyresampled(
      $dst,
      $src,
      ($dx - $sdx) / 2, ($dy - $sdy) / 2,
      0, 0,
      $sdx, $sdy,
      $x, $y);

    return $this->saveImageDataInAnyFormat($dst, $file->getMimeType());
  }

  private function applyMemeTo(
    PhabricatorFile $file,
    $upper_text,
    $lower_text) {
    $data = $file->loadFileData();
    $img = imagecreatefromstring($data);
    $phabricator_root = dirname(phutil_get_library_root('phabricator'));
    $font_root = $phabricator_root.'/resources/font/';
    $font_path = $font_root.'tuffy.ttf';
    if (Filesystem::pathExists($font_root.'impact.ttf')) {
      $font_path = $font_root.'impact.ttf';
    }
    $text_color = imagecolorallocate($img, 255, 255, 255);
    $border_color = imagecolorallocatealpha($img, 0, 0, 0, 110);
    $border_width = 4;
    $font_max = 200;
    $font_min = 5;
    for ($i = $font_max; $i > $font_min; $i--) {
      $fit = $this->doesTextBoundingBoxFitInImage(
        $img,
        $upper_text,
        $i,
        $font_path);
      if ($fit['doesfit']) {
        $x = ($fit['imgwidth'] - $fit['txtwidth']) / 2;
        $y = $fit['txtheight'] + 10;
        $this->makeImageWithTextBorder($img,
          $i,
          $x,
          $y,
          $text_color,
          $border_color,
          $border_width,
          $font_path,
          $upper_text);
        break;
      }
    }
    for ($i = $font_max; $i > $font_min; $i--) {
      $fit = $this->doesTextBoundingBoxFitInImage($img,
        $lower_text, $i, $font_path);
      if ($fit['doesfit']) {
        $x = ($fit['imgwidth'] - $fit['txtwidth']) / 2;
        $y = $fit['imgheight'] - 10;
        $this->makeImageWithTextBorder(
          $img,
          $i,
          $x,
          $y,
          $text_color,
          $border_color,
          $border_width,
          $font_path,
          $lower_text);
        break;
      }
    }
    return $this->saveImageDataInAnyFormat($img, $file->getMimeType());
  }

  private function makeImageWithTextBorder($img, $font_size, $x, $y,
    $color, $stroke_color, $bw, $font, $text) {
    $angle = 0;
    $bw = abs($bw);
    for ($c1 = $x - $bw; $c1 <= $x + $bw; $c1++) {
      for ($c2 = $y - $bw; $c2 <= $y + $bw; $c2++) {
        if (!(($c1 == $x - $bw || $x + $bw) &&
          $c2 == $y - $bw || $c2 == $y + $bw)) {
          $bg = imagettftext($img, $font_size,
            $angle, $c1, $c2, $stroke_color, $font, $text);
          }
        }
      }
    imagettftext($img, $font_size, $angle,
            $x , $y, $color , $font, $text);
  }

  private function doesTextBoundingBoxFitInImage($img,
    $text, $font_size, $font_path) {
    // Default Angle = 0
    $angle = 0;

    $bbox = imagettfbbox($font_size, $angle, $font_path, $text);
    $text_height = abs($bbox[3] - $bbox[5]);
    $text_width = abs($bbox[0] - $bbox[2]);
    return array(
      "doesfit" => ($text_height * 1.05 <= imagesy($img) / 2
        && $text_width * 1.05 <= imagesx($img)),
      "txtwidth" => $text_width,
      "txtheight" => $text_height,
      "imgwidth" => imagesx($img),
      "imgheight" => imagesy($img),
    );
  }

  private function saveImageDataInAnyFormat($data, $preferred_mime = '') {
    switch ($preferred_mime) {
      case 'image/gif': // GIF doesn't support true color.
      case 'image/png':
        if (function_exists('imagepng')) {
          ob_start();
          imagepng($data);
          return ob_get_clean();
        }
        break;
    }

    $img = null;

    if (function_exists('imagejpeg')) {
      ob_start();
      imagejpeg($data);
      $img = ob_get_clean();
    } else if (function_exists('imagepng')) {
      ob_start();
      imagepng($data);
      $img = ob_get_clean();
    } else if (function_exists('imagegif')) {
      ob_start();
      imagegif($data);
      $img = ob_get_clean();
    } else {
      throw new Exception("No image generation functions exist!");
    }

    return $img;
  }

}
