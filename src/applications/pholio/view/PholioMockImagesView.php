<?php

final class PholioMockImagesView extends AphrontView {

  private $mock;

  public function setMock(PholioMock $mock) {
    $this->mock = $mock;
  }

  public function render() {
    if (!$this->mock) {
      throw new Exception("Call setMock() before render()!");
    }

    $image = id(new PholioImage())->loadOneWhere(
      "mockid=%d",
      $this->mock->getID());

    $file = id(new PhabricatorFile())->loadOneWhere(
      "phid=%s",
      $image->getFilePHID());

    $image_tag = phutil_tag(
      'img',
        array(
          'src' => $file->getBestURI(),
          'class' => 'pholio-mock-image',
        ),
      '');

    return phutil_tag(
      'div',
        array(
          'class' => 'pholio-mock-image-container',
        ),
      $image_tag);
  }

}
