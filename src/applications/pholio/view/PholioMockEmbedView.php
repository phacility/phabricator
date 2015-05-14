<?php

final class PholioMockEmbedView extends AphrontView {

  private $mock;
  private $images = array();

  public function setMock(PholioMock $mock) {
    $this->mock = $mock;
    return $this;
  }

  public function setImages(array $images) {
    $this->images = $images;
    return $this;
  }

  public function render() {
    if (!$this->mock) {
      throw new PhutilInvalidStateException('setMock');
    }
    $mock = $this->mock;

    $images_to_show = array();
    $thumbnail = null;
    if (!empty($this->images)) {
      $images_to_show = array_intersect_key(
        $this->mock->getImages(), array_flip($this->images));
    }

    $xform = PhabricatorFileTransform::getTransformByKey(
      PhabricatorFileThumbnailTransform::TRANSFORM_PINBOARD);

    if ($images_to_show) {
      $image = head($images_to_show);
      $thumbfile = $image->getFile();
      $header = 'M'.$mock->getID().' '.$mock->getName().
        ' (#'.$image->getID().')';
      $uri = '/M'.$this->mock->getID().'/'.$image->getID().'/';
    } else {
      $thumbfile = $mock->getCoverFile();
      $header = 'M'.$mock->getID().' '.$mock->getName();
      $uri = '/M'.$this->mock->getID();
    }

    $thumbnail = $thumbfile->getURIForTransform($xform);
    list($x, $y) = $xform->getTransformedDimensions($thumbfile);

    $item = id(new PHUIPinboardItemView())
      ->setHeader($header)
      ->setURI($uri)
      ->setImageURI($thumbnail)
      ->setImageSize($x, $y)
      ->setDisabled($mock->isClosed())
      ->addIconCount('fa-picture-o', count($mock->getImages()))
      ->addIconCount('fa-trophy', $mock->getTokenCount());

    return $item;
  }

}
