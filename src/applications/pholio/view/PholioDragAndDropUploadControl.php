<?php

/**
 * @group pholio
 */
final class PholioDragAndDropUploadControl
  extends AphrontAbstractFormDragAndDropUploadControl {

  private $images;

  public function setImages(array $images) {
    assert_instances_of($images, 'PholioImage');
    $this->images = $images;
    return $this;
  }
  public function getImages() {
    return $this->images;
  }

  protected function getFileView() {
    return id(new PholioUploadedImageView())
      ->setImages($this->getImages());
  }

}
