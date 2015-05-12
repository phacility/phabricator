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
    $x = $this->dstX;
    $y = $this->dstY;

    $xformer = new PhabricatorImageTransformer();

    if ($y === null) {
      return $xformer->executePreviewTransform($file, $x);
    } else {
      return $xformer->executeThumbTransform($file, $x, $y);
    }
  }

}
