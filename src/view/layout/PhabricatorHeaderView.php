<?php

final class PhabricatorHeaderView extends AphrontView {

  private $objectName;
  private $header;
  private $tags = array();
  private $image;
  private $subheader;
  private $gradient;
  private $noBackground;
  private $bleedHeader;

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setObjectName($object_name) {
    $this->objectName = $object_name;
    return $this;
  }

  public function setNoBackground($nada) {
    $this->noBackground = $nada;
    return $this;
  }

  public function addTag(PhabricatorTagView $tag) {
    $this->tags[] = $tag;
    return $this;
  }

  public function setImage($uri) {
    $this->image = $uri;
    return $this;
  }

  public function setSubheader($subheader) {
    $this->subheader = $subheader;
    return $this;
  }

  public function setBleedHeader($bleed) {
    $this->bleedHeader = $bleed;
    return $this;
  }

  public function setGradient($gradient) {
    $this->gradient = $gradient;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-header-view-css');

    $classes = array();
    $classes[] = 'phabricator-header-shell';

    if ($this->noBackground) {
      $classes[] = 'phabricator-header-no-backgound';
    }

    if ($this->bleedHeader) {
      $classes[] = 'phabricator-bleed-header';
    }

    if ($this->gradient) {
      $classes[] = 'sprite-gradient';
      $classes[] = 'gradient-'.$this->gradient.'-header';
    }

    $image = null;
    if ($this->image) {
      $image = phutil_tag(
        'span',
        array(
          'class' => 'phabricator-header-image',
          'style' => 'background-image: url('.$this->image.')',
        ),
        '');
      $classes[] = 'phabricator-header-has-image';
    }

    $header = array();
    $header[] = $this->header;

    if ($this->objectName) {
      array_unshift(
        $header,
        phutil_tag(
          'a',
          array(
            'href' => '/'.$this->objectName,
          ),
          $this->objectName),
        ' ');
    }

    if ($this->tags) {
      $header[] = ' ';
      $header[] = phutil_tag(
        'span',
        array(
          'class' => 'phabricator-header-tags',
        ),
        array_interleave(' ', $this->tags));
    }

    if ($this->subheader) {
      $header[] = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-header-subheader',
        ),
        $this->subheader);
    }

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      array(
        $image,
        phutil_tag(
          'h1',
          array(
            'class' => 'phabricator-header-view',
          ),
          $header),
      ));
  }


}
