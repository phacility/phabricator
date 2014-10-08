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

  public function executeThumbTransform(
    PhabricatorFile $file,
    $x,
    $y) {

    $image = $this->crudelyScaleTo($file, $x, $y);

    return PhabricatorFile::newFromFileData(
      $image,
      array(
        'name' => 'thumb-'.$file->getName(),
        'canCDN' => true,
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
        'canCDN' => true,
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
        'canCDN' => true,
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

    $cropped = $this->applyScaleWithImagemagick($file, $x, $scaled_y);
    if ($cropped != null) {
      return $cropped;
    }

    $img = $this->applyScaleTo(
      $file,
      $x,
      $scaled_y);

    return self::saveImageDataInAnyFormat($img, $file->getMimeType());
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


  /**
   * Very crudely scale an image up or down to an exact size.
   */
  private function crudelyScaleTo(PhabricatorFile $file, $dx, $dy) {
    $scaled = $this->applyScaleWithImagemagick($file, $dx, $dy);

    if ($scaled != null) {
      return $scaled;
    }

    $dst = $this->applyScaleTo($file, $dx, $dy);
    return self::saveImageDataInAnyFormat($dst, $file->getMimeType());
  }

  private function getBlankDestinationFile($dx, $dy) {
    $dst = imagecreatetruecolor($dx, $dy);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));

    return $dst;
  }

  private function applyScaleTo(PhabricatorFile $file, $dx, $dy) {
    $data = $file->loadFileData();
    $src = imagecreatefromstring($data);

    $x = imagesx($src);
    $y = imagesy($src);

    $scale = min(($dx / $x), ($dy / $y), 1);

    $sdx = $scale * $x;
    $sdy = $scale * $y;

    $dst = $this->getBlankDestinationFile($dx, $dy);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));

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
    $metadata = $file->getMetadata();
    $x = idx($metadata, PhabricatorFile::METADATA_IMAGE_WIDTH);
    $y = idx($metadata, PhabricatorFile::METADATA_IMAGE_HEIGHT);

    if (!$x || !$y) {
      $data = $file->loadFileData();
      $src = imagecreatefromstring($data);

      $x = imagesx($src);
      $y = imagesy($src);
    }

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
      'sdy' => $sdy,
    );
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

    $dst = $this->getBlankDestinationFile($dx, $dy);

    imagecopyresampled(
      $dst,
      $src,
      ($dx - $sdx) / 2, ($dy - $sdy) / 2,
      0, 0,
      $sdx, $sdy,
      $x, $y);

    return self::saveImageDataInAnyFormat($dst, $file->getMimeType());
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

  private function applyScaleWithImagemagick(PhabricatorFile $file, $dx, $dy) {
    $img_type = $file->getMimeType();
    $imagemagick = PhabricatorEnv::getEnvConfig('files.enable-imagemagick');

    if ($img_type != 'image/gif' || $imagemagick == false) {
      return null;
    }

    $data = $file->loadFileData();
    $src = imagecreatefromstring($data);

    $x = imagesx($src);
    $y = imagesy($src);

    if (self::isEnormousGIF($x, $y)) {
      return null;
    }

    $scale = min(($dx / $x), ($dy / $y), 1);

    $sdx = $scale * $x;
    $sdy = $scale * $y;

    $input = new TempFile();
    Filesystem::writeFile($input, $data);

    $resized = new TempFile();

    $future = new ExecFuture(
      'convert %s -coalesce -resize %sX%s%s %s',
      $input,
      $sdx,
      $sdy,
      '!',
      $resized);

    // Don't spend more than 10 seconds resizing; just fail if it takes longer
    // than that.
    $future->setTimeout(10)->resolvex();

    return Filesystem::readFile($resized);
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

/* -(  Detecting Enormous Files  )------------------------------------------- */


  /**
   * Determine if an image is enormous (too large to transform).
   *
   * Attackers can perform a denial of service attack by uploading highly
   * compressible images with enormous dimensions but a very small filesize.
   * Transforming them (e.g., into thumbnails) may consume huge quantities of
   * memory and CPU relative to the resources required to transmit the file.
   *
   * In general, we respond to these images by declining to transform them, and
   * using a default thumbnail instead.
   *
   * @param int Width of the image, in pixels.
   * @param int Height of the image, in pixels.
   * @return bool True if this image is enormous (too large to transform).
   * @task enormous
   */
  public static function isEnormousImage($x, $y) {
    // This is just a sanity check, but if we don't have valid dimensions we
    // shouldn't be trying to transform the file.
    if (($x <= 0) || ($y <= 0)) {
      return true;
    }

    return ($x * $y) > (4096 * 4096);
  }


  /**
   * Determine if a GIF is enormous (too large to transform).
   *
   * For discussion, see @{method:isEnormousImage}. We need to be more
   * careful about GIFs, because they can also have a large number of frames
   * despite having a very small filesize. We're more conservative about
   * calling GIFs enormous than about calling images in general enormous.
   *
   * @param int Width of the GIF, in pixels.
   * @param int Height of the GIF, in pixels.
   * @return bool True if this image is enormous (too large to transform).
   * @task enormous
   */
  public static function isEnormousGIF($x, $y) {
    if (self::isEnormousImage($x, $y)) {
      return true;
    }

    return ($x * $y) > (800 * 800);
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
