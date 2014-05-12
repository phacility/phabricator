<?php

final class PHUIPinboardItemView extends AphrontView {

  private $imageURI;
  private $uri;
  private $header;
  private $iconBlock = array();

  private $imageWidth;
  private $imageHeight;

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function setImageURI($image_uri) {
    $this->imageURI = $image_uri;
    return $this;
  }

  public function setImageSize($x, $y) {
    $this->imageWidth = $x;
    $this->imageHeight = $y;
    return $this;
  }

  public function addIconCount($icon, $count) {
    $this->iconBlock[] = array($icon, $count);
    return $this;
  }

  public function render() {
    $header = null;
    if ($this->header) {
      $header = phutil_tag(
        'div',
        array(
          'class' => 'phui-pinboard-item-header '.
            'sprite-gradient gradient-lightblue-header',
        ),
        phutil_tag('a', array('href' => $this->uri), $this->header));
    }

    $image = phutil_tag(
      'a',
      array(
        'href' => $this->uri,
        'class' => 'phui-pinboard-item-image-link',
      ),
      phutil_tag(
        'img',
        array(
          'src'     => $this->imageURI,
          'width'   => $this->imageWidth,
          'height'  => $this->imageHeight,
        )));

    $icons = array();
    if ($this->iconBlock) {
      $icon_list = array();
      foreach ($this->iconBlock as $block) {
        $icon = id(new PHUIIconView())
          ->setIconFont($block[0].' lightgreytext')
          ->addClass('phui-pinboard-icon');

        $count = phutil_tag('span', array(), $block[1]);
        $icon_list[] = phutil_tag(
          'span',
          array(
            'class' => 'phui-pinboard-item-count',
          ),
          array($icon, $count));
      }
      $icons = phutil_tag(
        'div',
        array(
          'class' => 'phui-pinboard-icons',
        ),
        $icon_list);
    }

    $content = $this->renderChildren();
    if ($content) {
      $content = phutil_tag(
        'div',
        array(
          'class' => 'phui-pinboard-item-content',
        ),
        $content);
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-pinboard-item-view',
      ),
      array(
        $header,
        $image,
        $icons,
        $content,
      ));
  }

}
