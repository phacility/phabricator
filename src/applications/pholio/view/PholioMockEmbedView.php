<?php

final class PholioMockEmbedView extends AphrontView {

  private $mock;

  public function setMock(PholioMock $mock) {
    $this->mock = $mock;
    return $this;
  }

  public function render() {
    if (!$this->mock) {
      throw new Exception("Call setMock() before render()!");
    }

    require_celerity_resource('pholio-css');


    $mock_link = phutil_tag(
      'a',
      array(
        'href' => '/M'.$this->mock->getID(),
      ),
      'M'.$this->mock->getID().' '.$this->mock->getName());

    $mock_header = phutil_tag(
      'div',
      array(
        'class' => 'pholio-mock-embed-head',
      ),
      $mock_link);

    $thumbnails = array();
    foreach (array_slice($this->mock->getImages(), 0, 4) as $image) {
      $thumbfile = $image->getFile();

      $dimensions = PhabricatorImageTransformer::getPreviewDimensions(
        $thumbfile,
        140);

      $tag = phutil_tag(
        'img',
        array(
            'width' => $dimensions['sdx'],
            'height' => $dimensions['sdy'],
            'class' => 'pholio-mock-carousel-thumbnail',
            'src' => $thumbfile->getPreview140URI(),
            'style' => 'top: '.floor((140 - $dimensions['sdy'] ) / 2).'px',
          ));

        $thumbnails[] = javelin_tag(
          'a',
          array(
            'class' => 'pholio-mock-carousel-thumb-item',
            'href' => '/M'.$this->mock->getID().'/'.$image->getID().'/',
          ),
          $tag);
    }

    $mock_body = phutil_tag(
      'div',
      array(),
      $thumbnails);

    $icons_data = array(
      'image' => count($this->mock->getImages()),
      'like' => $this->mock->getTokenCount());

    $icon_list = array();
    foreach ($icons_data as $icon_name => $icon_value) {
      $icon = phutil_tag(
        'span',
         array(
           'class' =>
             'pholio-mock-embed-icon sprite-icon action-'.$icon_name.'-grey',
         ),
         ' ');
      $count = phutil_tag('span', array(), $icon_value);

      $icon_list[] = phutil_tag(
        'span',
        array(
          'class' => 'pholio-mock-embed-icons'
        ),
        array($icon, $count));
    }

    $mock_footer = phutil_tag(
      'div',
      array(
         'class' => 'pholio-mock-embed-footer',
      ),
      $icon_list);


    $mock_view = phutil_tag(
      'div',
      array(
        'class' => 'pholio-mock-embed'
      ),
      array($mock_header, $mock_body, $mock_footer));

    return $this->renderSingleView($mock_view);
  }
}
