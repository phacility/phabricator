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

    $mockview = "";

    $file = head($this->mock->getImages())->getFile();

    $main_image_id = celerity_generate_unique_node_id();

    $main_image_tag = phutil_render_tag(
      'img',
      array(
        'src' => $file->getBestURI(),
        'class' => 'pholio-mock-image',
        'id' => $main_image_id,
      ));

    $mockview .= phutil_render_tag(
      'div',
        array(
          'class' => 'pholio-mock-image-container',
        ),
      $main_image_tag);

    if (count($this->mock->getImages()) > 1) {
      require_celerity_resource('javelin-behavior-pholio-mock-view');
      $config = array('mainID' => $main_image_id);
      Javelin::initBehavior('pholio-mock-view', $config);

      $thumbnails = array();
      foreach ($this->mock->getImages() as $image) {
        $thumbfile = $image->getFile();

        $tag = javelin_render_tag(
          'img',
          array(
            'src' => $thumbfile->getThumb160x120URI(),
            'sigil' => 'mock-thumbnail',
            'class' => 'pholio-mock-carousel-thumbnail',
            'meta' => array(
              'fullSizeURI' => $thumbfile->getBestURI(),
              'imageID' => $image->getID(),
            ),
        ));
        $thumbnails[] = $tag;
      }

      $mockview .= phutil_render_tag(
        'div',
          array(
            'class' => 'pholio-mock-carousel',
          ),
        implode($thumbnails));
    }

    return $mockview;
  }

}
