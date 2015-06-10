<?php

final class PHUIPinboardItemView extends AphrontView {

  private $imageURI;
  private $uri;
  private $header;
  private $iconBlock = array();
  private $disabled;
  private $object;
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

  public function setDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function render() {
    require_celerity_resource('phui-pinboard-view-css');
    $header = null;
    if ($this->header) {
      if ($this->disabled) {
        $header_color = 'gradient-lightgrey-header';
      } else {
        $header_color = 'gradient-lightblue-header';
      }
      $header = phutil_tag(
        'div',
        array(
          'class' => 'phui-pinboard-item-header '.
            'sprite-gradient '.$header_color,
        ),
        array(
          id(new PHUISpacesNamespaceContextView())
            ->setUser($this->getUser())
            ->setObject($this->object),
          phutil_tag(
            'a',
            array(
              'href' => $this->uri,
            ),
            $this->header),
        ));
    }

    $image = null;
    if ($this->imageWidth) {
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
    }

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

    $classes = array();
    $classes[] = 'phui-pinboard-item-view';
    if ($this->disabled) {
      $classes[] = 'phui-pinboard-item-disabled';
    }

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      array(
        $header,
        $image,
        $content,
        $icons,
      ));
  }

}
