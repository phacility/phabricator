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

    $file = head($this->mock->getImages())->getFile();

    $image_tag = phutil_render_tag(
      'img',
        array(
          'src' => $file->getBestURI(),
          'class' => 'pholio-mock-image',
        ),
      '');

    return phutil_render_tag(
      'div',
        array(
          'class' => 'pholio-mock-image-container',
        ),
      $image_tag);
  }

}
