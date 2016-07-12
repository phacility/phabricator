<?php

abstract class PhabricatorFileImageTransform extends PhabricatorFileTransform {

  private $file;
  private $data;
  private $image;
  private $imageX;
  private $imageY;

  /**
   * Get an estimate of the transformed dimensions of a file.
   *
   * @param PhabricatorFile File to transform.
   * @return list<int, int>|null Width and height, if available.
   */
  public function getTransformedDimensions(PhabricatorFile $file) {
    return null;
  }

  public function canApplyTransform(PhabricatorFile $file) {
    if (!$file->isViewableImage()) {
      return false;
    }

    if (!$file->isTransformableImage()) {
      return false;
    }

    return true;
  }

  protected function willTransformFile(PhabricatorFile $file) {
    $this->file = $file;
    $this->data = null;
    $this->image = null;
    $this->imageX = null;
    $this->imageY = null;
  }

  protected function getFileProperties() {
    return array();
  }

  protected function applyCropAndScale(
    $dst_w, $dst_h,
    $src_x, $src_y,
    $src_w, $src_h,
    $use_w, $use_h,
    $scale_up) {

    // Figure out the effective destination width, height, and offsets.
    $cpy_w = min($dst_w, $use_w);
    $cpy_h = min($dst_h, $use_h);

    // If we aren't scaling up, and are copying a very small source image,
    // we're just going to center it in the destination image.
    if (!$scale_up) {
      $cpy_w = min($cpy_w, $src_w);
      $cpy_h = min($cpy_h, $src_h);
    }

    $off_x = ($dst_w - $cpy_w) / 2;
    $off_y = ($dst_h - $cpy_h) / 2;

    if ($this->shouldUseImagemagick()) {
      $argv = array();
      $argv[] = '-coalesce';
      $argv[] = '-shave';
      $argv[] = $src_x.'x'.$src_y;
      $argv[] = '-resize';

      if ($scale_up) {
        $argv[] = $dst_w.'x'.$dst_h;
      } else {
        $argv[] = $dst_w.'x'.$dst_h.'>';
      }

      $argv[] = '-bordercolor';
      $argv[] = 'rgba(255, 255, 255, 0)';
      $argv[] = '-border';
      $argv[] = $off_x.'x'.$off_y;

      return $this->applyImagemagick($argv);
    }

    $src = $this->getImage();
    $dst = $this->newEmptyImage($dst_w, $dst_h);

    $trap = new PhutilErrorTrap();
    $ok = @imagecopyresampled(
      $dst,
      $src,
      $off_x, $off_y,
      $src_x, $src_y,
      $cpy_w, $cpy_h,
      $src_w, $src_h);
    $errors = $trap->getErrorsAsString();
    $trap->destroy();

    if ($ok === false) {
      throw new Exception(
        pht(
          'Failed to imagecopyresampled() image: %s',
          $errors));
    }

    $data = PhabricatorImageTransformer::saveImageDataInAnyFormat(
      $dst,
      $this->file->getMimeType());

    return $this->newFileFromData($data);
  }

  protected function applyImagemagick(array $argv) {
    $tmp = new TempFile();
    Filesystem::writeFile($tmp, $this->getData());

    $out = new TempFile();

    $future = new ExecFuture('convert %s %Ls %s', $tmp, $argv, $out);
    // Don't spend more than 60 seconds resizing; just fail if it takes longer
    // than that.
    $future->setTimeout(60)->resolvex();

    $data = Filesystem::readFile($out);

    return $this->newFileFromData($data);
  }


  /**
   * Create a new @{class:PhabricatorFile} from raw data.
   *
   * @param string Raw file data.
   */
  protected function newFileFromData($data) {
    if ($this->file) {
      $name = $this->file->getName();
    } else {
      $name = 'default.png';
    }

    $defaults = array(
      'canCDN' => true,
      'name' => $this->getTransformKey().'-'.$name,
    );

    $properties = $this->getFileProperties() + $defaults;

    return PhabricatorFile::newFromFileData($data, $properties);
  }


  /**
   * Create a new image filled with transparent pixels.
   *
   * @param int Desired image width.
   * @param int Desired image height.
   * @return resource New image resource.
   */
  protected function newEmptyImage($w, $h) {
    $w = (int)$w;
    $h = (int)$h;

    if (($w <= 0) || ($h <= 0)) {
      throw new Exception(
        pht('Can not create an image with nonpositive dimensions.'));
    }

    $trap = new PhutilErrorTrap();
    $img = @imagecreatetruecolor($w, $h);
    $errors = $trap->getErrorsAsString();
    $trap->destroy();
    if ($img === false) {
      throw new Exception(
        pht(
          'Unable to imagecreatetruecolor() a new empty image: %s',
          $errors));
    }

    $trap = new PhutilErrorTrap();
    $ok = @imagesavealpha($img, true);
    $errors = $trap->getErrorsAsString();
    $trap->destroy();
    if ($ok === false) {
      throw new Exception(
        pht(
          'Unable to imagesavealpha() a new empty image: %s',
          $errors));
    }

    $trap = new PhutilErrorTrap();
    $color = @imagecolorallocatealpha($img, 255, 255, 255, 127);
    $errors = $trap->getErrorsAsString();
    $trap->destroy();
    if ($color === false) {
      throw new Exception(
        pht(
          'Unable to imagecolorallocatealpha() a new empty image: %s',
          $errors));
    }

    $trap = new PhutilErrorTrap();
    $ok = @imagefill($img, 0, 0, $color);
    $errors = $trap->getErrorsAsString();
    $trap->destroy();
    if ($ok === false) {
      throw new Exception(
        pht(
          'Unable to imagefill() a new empty image: %s',
          $errors));
    }

    return $img;
  }


  /**
   * Get the pixel dimensions of the image being transformed.
   *
   * @return list<int, int> Width and height of the image.
   */
  protected function getImageDimensions() {
    if ($this->imageX === null) {
      $image = $this->getImage();

      $trap = new PhutilErrorTrap();
      $x = @imagesx($image);
      $y = @imagesy($image);
      $errors = $trap->getErrorsAsString();
      $trap->destroy();

      if (($x === false) || ($y === false) || ($x <= 0) || ($y <= 0)) {
        throw new Exception(
          pht(
            'Unable to determine image dimensions with '.
            'imagesx()/imagesy(): %s',
            $errors));
      }

      $this->imageX = $x;
      $this->imageY = $y;
    }

    return array($this->imageX, $this->imageY);
  }


  /**
   * Get the raw file data for the image being transformed.
   *
   * @return string Raw file data.
   */
  protected function getData() {
    if ($this->data !== null) {
      return $this->data;
    }

    $file = $this->file;

    $max_size = (1024 * 1024 * 16);
    $img_size = $file->getByteSize();
    if ($img_size > $max_size) {
      throw new Exception(
        pht(
          'This image is too large to transform. The transform limit is %s '.
          'bytes, but the image size is %s bytes.',
          new PhutilNumber($max_size),
          new PhutilNumber($img_size)));
    }

    $data = $file->loadFileData();
    $this->data = $data;
    return $this->data;
  }


  /**
   * Get the GD image resource for the image being transformed.
   *
   * @return resource GD image resource.
   */
  protected function getImage() {
    if ($this->image !== null) {
      return $this->image;
    }

    if (!function_exists('imagecreatefromstring')) {
      throw new Exception(
        pht(
          'Unable to transform image: the imagecreatefromstring() function '.
          'is not available. Install or enable the "gd" extension for PHP.'));
    }

    $data = $this->getData();
    $data = (string)$data;

    // First, we're going to write the file to disk and use getimagesize()
    // to determine its dimensions without actually loading the pixel data
    // into memory. For very large images, we'll bail out.

    // In particular, this defuses a resource exhaustion attack where the
    // attacker uploads a 40,000 x 40,000 pixel PNGs of solid white. These
    // kinds of files compress extremely well, but require a huge amount
    // of memory and CPU to process.

    $tmp = new TempFile();
    Filesystem::writeFile($tmp, $data);
    $tmp_path = (string)$tmp;

    $trap = new PhutilErrorTrap();
    $info = @getimagesize($tmp_path);
    $errors = $trap->getErrorsAsString();
    $trap->destroy();

    unset($tmp);

    if ($info === false) {
      throw new Exception(
        pht(
          'Unable to get image information with getimagesize(): %s',
          $errors));
    }

    list($width, $height) = $info;
    if (($width <= 0) || ($height <= 0)) {
      throw new Exception(
        pht(
          'Unable to determine image width and height with getimagesize().'));
    }

    $max_pixels = (4096 * 4096);
    $img_pixels = ($width * $height);

    if ($img_pixels > $max_pixels) {
      throw new Exception(
        pht(
          'This image (with dimensions %spx x %spx) is too large to '.
          'transform. The image has %s pixels, but transforms are limited '.
          'to images with %s or fewer pixels.',
          new PhutilNumber($width),
          new PhutilNumber($height),
          new PhutilNumber($img_pixels),
          new PhutilNumber($max_pixels)));
    }

    $trap = new PhutilErrorTrap();
    $image = @imagecreatefromstring($data);
    $errors = $trap->getErrorsAsString();
    $trap->destroy();

    if ($image === false) {
      throw new Exception(
        pht(
          'Unable to load image data with imagecreatefromstring(): %s',
          $errors));
    }

    $this->image = $image;
    return $this->image;
  }

  private function shouldUseImagemagick() {
    if (!PhabricatorEnv::getEnvConfig('files.enable-imagemagick')) {
      return false;
    }

    if ($this->file->getMimeType() != 'image/gif') {
      return false;
    }

    // Don't try to preserve the animation in huge GIFs.
    list($x, $y) = $this->getImageDimensions();
    if (($x * $y) > (512 * 512)) {
      return false;
    }

    return true;
  }

}
