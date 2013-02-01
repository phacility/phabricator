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

    $main_image_id = celerity_generate_unique_node_id();
    require_celerity_resource('javelin-behavior-pholio-mock-view');
    $config = array('mainID' => $main_image_id);
    Javelin::initBehavior('pholio-mock-view', $config);

    $mockview = "";

    $main_image = head($this->mock->getImages());

      $main_image_tag = javelin_render_tag(
      'img',
      array(
        'id' => $main_image_id,
        'src' => $main_image->getFile()->getBestURI(),
        'sigil' => 'mock-image',
        'class' => 'pholio-mock-image',
        'meta' => array(
          'fullSizeURI' => $main_image->getFile()->getBestURI(),
          'imageID' => $main_image->getID(),
        ),
    ));

    $main_image_tag = javelin_render_tag(
      'div',
      array(
        'id' => 'mock-wrapper',
        'sigil' => 'mock-wrapper',
        'class' => 'pholio-mock-wrapper'
      ),
      $main_image_tag
    );

    $mockview .= phutil_render_tag(
      'div',
        array(
          'class' => 'pholio-mock-image-container',
        ),
      $main_image_tag);

    if (count($this->mock->getImages()) > 1) {
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
              'imageID' => $image->getID()
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
