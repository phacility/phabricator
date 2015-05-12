<?php

final class PhabricatorFileThumbnailTransform
  extends PhabricatorFileImageTransform {

  const TRANSFORM_PROFILE = 'profile';
  const TRANSFORM_PINBOARD = 'pinboard';
  const TRANSFORM_THUMBGRID = 'thumbgrid';
  const TRANSFORM_PREVIEW = 'preview';

  private $name;
  private $key;
  private $dstX;
  private $dstY;

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

  public function getTransformName() {
    return $this->name;
  }

  public function getTransformKey() {
    return $this->key;
  }

  public function generateTransforms() {
    return array(
      id(new PhabricatorFileThumbnailTransform())
        ->setName(pht("Profile (100px \xC3\x97 100px)"))
        ->setKey(self::TRANSFORM_PROFILE)
        ->setDimensions(100, 100),
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
    );
  }

  public function applyTransform(PhabricatorFile $file) {
    $xformer = new PhabricatorImageTransformer();
    if ($this->dstY === null) {
      return $xformer->executePreviewTransform($file, $this->dstX);
    }

    $this->willTransformFile($file);

    list($src_x, $src_y) = $this->getImageDimensions();
    $dst_x = $this->dstX;
    $dst_y = $this->dstY;

    // Figure out how much we'd have to scale the image down along each
    // dimension to get the entire thing to fit.
    $scale_x = min(($dst_x / $src_x), 1);
    $scale_y = min(($dst_y / $src_y), 1);

    if ($scale_x > $scale_y) {
      // This image is relatively tall and narrow. We're going to crop off the
      // top and bottom.
      $copy_x = $src_x;
      $copy_y = min($src_y, $dst_y / $scale_x);
    } else {
      // This image is relatively short and wide. We're going to crop off the
      // left and right.
      $copy_x = min($src_x, $dst_x / $scale_y);
      $copy_y = $src_y;
    }

    return $this->applyCropAndScale(
      $dst_x,
      $dst_y,
      ($src_x - $copy_x) / 2,
      ($src_y - $copy_y) / 2,
      $copy_x,
      $copy_y);
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
