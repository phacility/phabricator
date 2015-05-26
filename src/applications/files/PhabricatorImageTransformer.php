<?php

/**
 * @task enormous Detecting Enormous Images
 * @task save     Saving Image Data
 */
final class PhabricatorImageTransformer {

  public function executeMemeTransform(
    PhabricatorFile $file,
    $upper_text,
    $lower_text) {
    $image = $this->applyMemeToFile($file, $upper_text, $lower_text);
    return PhabricatorFile::newFromFileData(
      $image,
      array(
        'name' => 'meme-'.$file->getName(),
        'ttl' => time() + 60 * 60 * 24,
        'canCDN' => true,
      ));
  }

  public function executeConpherenceTransform(
    PhabricatorFile $file,
    $top,
    $left,
    $width,
    $height) {

    $image = $this->crasslyCropTo(
      $file,
      $top,
      $left,
      $width,
      $height);

    return PhabricatorFile::newFromFileData(
      $image,
      array(
        'name' => 'conpherence-'.$file->getName(),
        'profile' => true,
        'canCDN' => true,
      ));
  }

  private function crasslyCropTo(PhabricatorFile $file, $top, $left, $w, $h) {
    $data = $file->loadFileData();
    $src = imagecreatefromstring($data);
    $dst = $this->getBlankDestinationFile($w, $h);

    $scale = self::getScaleForCrop($file, $w, $h);
    $orig_x = $left / $scale;
    $orig_y = $top / $scale;
    $orig_w = $w / $scale;
    $orig_h = $h / $scale;

    imagecopyresampled(
      $dst,
      $src,
      0, 0,
      $orig_x, $orig_y,
      $w, $h,
      $orig_w, $orig_h);

    return self::saveImageDataInAnyFormat($dst, $file->getMimeType());
  }

  private function getBlankDestinationFile($dx, $dy) {
    $dst = imagecreatetruecolor($dx, $dy);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));

    return $dst;
  }

  public static function getScaleForCrop(
    PhabricatorFile $file,
    $des_width,
    $des_height) {

    $metadata = $file->getMetadata();
    $width = $metadata[PhabricatorFile::METADATA_IMAGE_WIDTH];
    $height = $metadata[PhabricatorFile::METADATA_IMAGE_HEIGHT];

    if ($height < $des_height) {
      $scale = $height / $des_height;
    } else if ($width < $des_width) {
      $scale = $width / $des_width;
    } else {
      $scale_x = $des_width / $width;
      $scale_y = $des_height / $height;
      $scale = max($scale_x, $scale_y);
    }

    return $scale;
  }

  private function applyMemeToFile(
    PhabricatorFile $file,
    $upper_text,
    $lower_text) {
    $data = $file->loadFileData();

    $img_type = $file->getMimeType();
    $imagemagick = PhabricatorEnv::getEnvConfig('files.enable-imagemagick');

    if ($img_type != 'image/gif' || $imagemagick == false) {
      return $this->applyMemeTo(
        $data, $upper_text, $lower_text, $img_type);
    }

    $data = $file->loadFileData();
    $input = new TempFile();
    Filesystem::writeFile($input, $data);

    list($out) = execx('convert %s info:', $input);
    $split = phutil_split_lines($out);
    if (count($split) > 1) {
      return $this->applyMemeWithImagemagick(
        $input,
        $upper_text,
        $lower_text,
        count($split),
        $img_type);
    } else {
      return $this->applyMemeTo($data, $upper_text, $lower_text, $img_type);
    }
  }

  private function applyMemeTo(
    $data,
    $upper_text,
    $lower_text,
    $mime_type) {
    $img = imagecreatefromstring($data);

    // Some PNGs have color palettes, and allocating the dark border color
    // fails and gives us whatever's first in the color table. Copy the image
    // to a fresh truecolor canvas before working with it.

    $truecolor = imagecreatetruecolor(imagesx($img), imagesy($img));
    imagecopy($truecolor, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
    $img = $truecolor;

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
    return self::saveImageDataInAnyFormat($img, $mime_type);
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
      'doesfit' => ($text_height * 1.05 <= imagesy($img) / 2
        && $text_width * 1.05 <= imagesx($img)),
      'txtwidth' => $text_width,
      'txtheight' => $text_height,
      'imgwidth' => imagesx($img),
      'imgheight' => imagesy($img),
    );
  }

  private function applyMemeWithImagemagick(
    $input,
    $above,
    $below,
    $count,
    $img_type) {

    $output = new TempFile();
    $future = new ExecFuture(
      'convert %s -coalesce +adjoin %s_%s',
      $input,
      $input,
      '%09d');
    $future->setTimeout(10)->resolvex();

    $output_files = array();
    for ($ii = 0; $ii < $count; $ii++) {
      $frame_name = sprintf('%s_%09d', $input, $ii);
      $output_name = sprintf('%s_%09d', $output, $ii);

      $output_files[] = $output_name;

      $frame_data = Filesystem::readFile($frame_name);
      $memed_frame_data = $this->applyMemeTo(
        $frame_data,
        $above,
        $below,
        $img_type);
      Filesystem::writeFile($output_name, $memed_frame_data);
    }

    $future = new ExecFuture('convert -loop 0 %Ls %s', $output_files, $output);
    $future->setTimeout(10)->resolvex();

    return Filesystem::readFile($output);
  }


/* -(  Saving Image Data  )-------------------------------------------------- */


  /**
   * Save an image resource to a string representation suitable for storage or
   * transmission as an image file.
   *
   * Optionally, you can specify a preferred MIME type like `"image/png"`.
   * Generally, you should specify the MIME type of the original file if you're
   * applying file transformations. The MIME type may not be honored if
   * Phabricator can not encode images in the given format (based on available
   * extensions), but can save images in another format.
   *
   * @param   resource  GD image resource.
   * @param   string?   Optionally, preferred mime type.
   * @return  string    Bytes of an image file.
   * @task save
   */
  public static function saveImageDataInAnyFormat($data, $preferred_mime = '') {
    $preferred = null;
    switch ($preferred_mime) {
      case 'image/gif':
        $preferred = self::saveImageDataAsGIF($data);
        break;
      case 'image/png':
        $preferred = self::saveImageDataAsPNG($data);
        break;
    }

    if ($preferred !== null) {
      return $preferred;
    }

    $data = self::saveImageDataAsJPG($data);
    if ($data !== null) {
      return $data;
    }

    $data = self::saveImageDataAsPNG($data);
    if ($data !== null) {
      return $data;
    }

    $data = self::saveImageDataAsGIF($data);
    if ($data !== null) {
      return $data;
    }

    throw new Exception(pht('Failed to save image data into any format.'));
  }


  /**
   * Save an image in PNG format, returning the file data as a string.
   *
   * @param resource      GD image resource.
   * @return string|null  PNG file as a string, or null on failure.
   * @task save
   */
  private static function saveImageDataAsPNG($image) {
    if (!function_exists('imagepng')) {
      return null;
    }

    ob_start();
    $result = imagepng($image, null, 9);
    $output = ob_get_clean();

    if (!$result) {
      return null;
    }

    return $output;
  }


  /**
   * Save an image in GIF format, returning the file data as a string.
   *
   * @param resource      GD image resource.
   * @return string|null  GIF file as a string, or null on failure.
   * @task save
   */
  private static function saveImageDataAsGIF($image) {
    if (!function_exists('imagegif')) {
      return null;
    }

    ob_start();
    $result = imagegif($image);
    $output = ob_get_clean();

    if (!$result) {
      return null;
    }

    return $output;
  }


  /**
   * Save an image in JPG format, returning the file data as a string.
   *
   * @param resource      GD image resource.
   * @return string|null  JPG file as a string, or null on failure.
   * @task save
   */
  private static function saveImageDataAsJPG($image) {
    if (!function_exists('imagejpeg')) {
      return null;
    }

    ob_start();
    $result = imagejpeg($image);
    $output = ob_get_clean();

    if (!$result) {
      return null;
    }

    return $output;
  }


}
