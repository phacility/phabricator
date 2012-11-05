<?php

final class PhabricatorPinboardItemView extends AphrontView {

  private $imageURI;
  private $uri;
  private $header;

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

  public function render() {
    $header = null;
    if ($this->header) {
      $header = hsprintf('<a href="%s">%s</a>', $this->uri, $this->header);
      $header = phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-pinboard-item-header',
        ),
        $header);
    }

    $image = phutil_render_tag(
      'a',
      array(
        'href' => $this->uri,
        'class' => 'phabricator-pinboard-item-image-link',
      ),
      phutil_render_tag(
        'img',
        array(
          'src'     => $this->imageURI,
          'width'   => $this->imageWidth,
          'height'  => $this->imageHeight,
        )));

    $content = $this->renderChildren();
    if ($content) {
      $content = phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-pinboard-item-content',
        ),
        $content);
    }

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-pinboard-item-view',
      ),
      $header.
      $image.
      $content);
  }

}
