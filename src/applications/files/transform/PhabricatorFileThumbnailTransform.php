<?php

final class PhabricatorFileThumbnailTransform
  extends PhabricatorFileImageTransform {

  const TRANSFORM_PROFILE = 'profile';
  const TRANSFORM_PINBOARD = 'pinboard';
  const TRANSFORM_THUMBGRID = 'thumbgrid';
  const TRANSFORM_PREVIEW = 'preview';
  const TRANSFORM_WORKCARD = 'workcard';

  private $name;
  private $key;
  private $dstX;
  private $dstY;
  private $scaleUp;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function setDimensions($x, $y) {
    $this->dstX = $x;
    $this->dstY = $y;
    return $this;
  }

  public function setScaleUp($scale) {
    $this->scaleUp = $scale;
    return $this;
  }

  public function getTransformName() {
    return $this->name;
  }

  public function getTransformKey() {
    return $this->key;
  }

  protected function getFileProperties() {
    $properties = array();
    switch ($this->key) {
      case self::TRANSFORM_PROFILE:
        $properties['profile'] = true;
        $properties['name'] = 'profile';
        break;
    }
    return $properties;
  }

  public function generateTransforms() {
    return array(
      id(new PhabricatorFileThumbnailTransform())
        ->setName(pht("Profile (400px \xC3\x97 400px)"))
        ->setKey(self::TRANSFORM_PROFILE)
        ->setDimensions(400, 400)
        ->setScaleUp(true),
      id(new PhabricatorFileThumbnailTransform())
        ->setName(pht("Pinboard (280px \xC3\x97 210px)"))
        ->setKey(self::TRANSFORM_PINBOARD)
        ->setDimensions(280, 210),
      id(new PhabricatorFileThumbnailTransform())
        ->setName(pht('Thumbgrid (100px)'))
        ->setKey(self::TRANSFORM_THUMBGRID)
        ->setDimensions(100, null),
      id(new PhabricatorFileThumbnailTransform())
        ->setName(pht('Preview (220px)'))
        ->setKey(self::TRANSFORM_PREVIEW)
        ->setDimensions(220, null),
      id(new self())
        ->setName(pht('Workcard (526px)'))
        ->setKey(self::TRANSFORM_WORKCARD)
        ->setScaleUp(true)
        ->setDimensions(526, null),
    );
  }

  public function applyTransform(PhabricatorFile $file) {
    $this->willTransformFile($file);

    list($src_x, $src_y) = $this->getImageDimensions();
    $dst_x = $this->dstX;
    $dst_y = $this->dstY;

    $dimensions = $this->computeDimensions(
      $src_x,
      $src_y,
      $dst_x,
      $dst_y);

    $copy_x = $dimensions['copy_x'];
    $copy_y = $dimensions['copy_y'];
    $use_x = $dimensions['use_x'];
    $use_y = $dimensions['use_y'];
    $dst_x = $dimensions['dst_x'];
    $dst_y = $dimensions['dst_y'];

    return $this->applyCropAndScale(
      $dst_x,
      $dst_y,
      ($src_x - $copy_x) / 2,
      ($src_y - $copy_y) / 2,
      $copy_x,
      $copy_y,
      $use_x,
      $use_y,
      $this->scaleUp);
  }


  public function getTransformedDimensions(PhabricatorFile $file) {
    $dst_x = $this->dstX;
    $dst_y = $this->dstY;

    // If this is transform has fixed dimensions, we can trivially predict
    // the dimensions of the transformed file.
    if ($dst_y !== null) {
      return array($dst_x, $dst_y);
    }

    $src_x = $file->getImageWidth();
    $src_y = $file->getImageHeight();

    if (!$src_x || !$src_y) {
      return null;
    }

    $dimensions = $this->computeDimensions(
      $src_x,
      $src_y,
      $dst_x,
      $dst_y);

    return array($dimensions['dst_x'], $dimensions['dst_y']);
  }


  private function computeDimensions($src_x, $src_y, $dst_x, $dst_y) {
    if ($dst_y === null) {
      // If we only have one dimension, it represents a maximum dimension.
      // The other dimension of the transform is scaled appropriately, except
      // that we never generate images with crazily extreme aspect ratios.
      if ($src_x < $src_y) {
        // This is a tall, narrow image. Use the maximum dimension for the
        // height and scale the width.
        $use_y = $dst_x;
        $dst_y = $dst_x;

        $use_x = $dst_y * ($src_x / $src_y);
        $dst_x = max($dst_y / 4, $use_x);
      } else {
        // This is a short, wide image. Use the maximum dimension for the width
        // and scale the height.
        $use_x = $dst_x;

        $use_y = $dst_x * ($src_y / $src_x);
        $dst_y = max($dst_x / 4, $use_y);
      }

      // In this mode, we always copy the entire source image. We may generate
      // margins in the output.
      $copy_x = $src_x;
      $copy_y = $src_y;
    } else {
      $scale_up = $this->scaleUp;

      // Otherwise, both dimensions are fixed. Figure out how much we'd have to
      // scale the image down along each dimension to get the entire thing to
      // fit.
      $scale_x = ($dst_x / $src_x);
      $scale_y = ($dst_y / $src_y);

      if (!$scale_up) {
        $scale_x = min($scale_x, 1);
        $scale_y = min($scale_y, 1);
      }

      if ($scale_x > $scale_y) {
        // This image is relatively tall and narrow. We're going to crop off the
        // top and bottom.
        $scale = $scale_x;
      } else {
        // This image is relatively short and wide. We're going to crop off the
        // left and right.
        $scale = $scale_y;
      }

      $copy_x = $dst_x / $scale;
      $copy_y = $dst_y / $scale;

      if (!$scale_up) {
        $copy_x = min($src_x, $copy_x);
        $copy_y = min($src_y, $copy_y);
      }

      // In this mode, we always use the entire destination image. We may
      // crop the source input.
      $use_x = $dst_x;
      $use_y = $dst_y;
    }

    return array(
      'copy_x' => $copy_x,
      'copy_y' => $copy_y,
      'use_x' => $use_x,
      'use_y' => $use_y,
      'dst_x' => $dst_x,
      'dst_y' => $dst_y,
    );
  }


  public function getDefaultTransform(PhabricatorFile $file) {
    $x = (int)$this->dstX;
    $y = (int)$this->dstY;
    $name = 'image-'.$x.'x'.nonempty($y, $x).'.png';

    $root = dirname(phutil_get_library_root('phabricator'));
    $data = Filesystem::readFile($root.'/resources/builtin/'.$name);

    return $this->newFileFromData($data);
  }

}
