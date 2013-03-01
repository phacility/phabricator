<?php

final class PholioMockImagesView extends AphrontView {

  private $mock;

  public function setMock(PholioMock $mock) {
    $this->mock = $mock;
    return $this;
  }

  public function render() {
    if (!$this->mock) {
      throw new Exception("Call setMock() before render()!");
    }

    $mock = $this->mock;

    require_celerity_resource('javelin-behavior-pholio-mock-view');

    $images = array();
    $panel_id = celerity_generate_unique_node_id();
    $viewport_id = celerity_generate_unique_node_id();

    foreach ($mock->getImages() as $image) {
      $images[] = array(
        'id'      => $image->getID(),
        'fullURI' => $image->getFile()->getBestURI(),
      );
    }

    $config = array(
      'mockID' => $mock->getID(),
      'panelID' => $panel_id,
      'viewportID' => $viewport_id,
      'images' => $images,

    );
    Javelin::initBehavior('pholio-mock-view', $config);

    $mockview = '';

    $mock_wrapper = phutil_tag(
      'div',
      array(
        'id' => $viewport_id,
        'class' => 'pholio-mock-image-viewport'
      ),
      '');

    $mock_wrapper = javelin_tag(
      'div',
      array(
        'id' => $panel_id,
        'sigil' => 'mock-panel',
        'class' => 'pholio-mock-image-panel',
      ),
      $mock_wrapper);

    $inline_comments_holder = javelin_tag(
      'div',
      array(
        'id' => 'mock-inline-comments',
        'sigil' => 'mock-inline-comments',
        'class' => 'pholio-mock-inline-comments'
      ),
      '');

    $mockview[] = phutil_tag(
      'div',
        array(
          'class' => 'pholio-mock-image-container',
          'id' => 'pholio-mock-image-container'
        ),
      array($mock_wrapper, $inline_comments_holder));

    if (count($mock->getImages()) > 0) {
      $thumbnails = array();
      foreach ($mock->getImages() as $image) {
        $thumbfile = $image->getFile();

        $dimensions = PhabricatorImageTransformer::getPreviewDimensions(
          $thumbfile,
          140);

        $tag = phutil_tag(
          'img',
          array(
            'width' => $dimensions['sdx'],
            'height' => $dimensions['sdy'],
            'src' => $thumbfile->getPreview140URI(),
            'class' => 'pholio-mock-carousel-thumbnail',
            'style' => 'top: '.floor((140 - $dimensions['sdy'] ) / 2).'px',
        ));

        $thumbnails[] = javelin_tag(
          'div',
          array(
            'sigil' => 'mock-thumbnail',
            'class' => 'pholio-mock-carousel-thumb-item',
            'meta' => array(
              'imageID' => $image->getID(),
            ),
          ),
          $tag);
      }

      $mockview[] = phutil_tag(
        'div',
        array(
          'id' => 'pholio-mock-carousel',
          'class' => 'pholio-mock-carousel',
        ),
        $thumbnails);
    }

    return $this->renderSingleView($mockview);
  }
}
