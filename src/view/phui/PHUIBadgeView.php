<?php

final class PHUIBadgeView extends AphrontTagView {

  private $href;
  private $icon;
  private $quality;
  private $source;
  private $header;
  private $subhead;
  private $bylines = array();

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setQuality($quality) {
    $this->quality = $quality;
    return $this;
  }

  private function getQualityColor() {
    return PhabricatorBadgesQuality::getQualityColor($this->quality);
  }

  private function getQualityName() {
    return PhabricatorBadgesQuality::getQualityName($this->quality);
  }

  public function setSource($source) {
    $this->source = $source;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setSubhead($subhead) {
    $this->subhead = $subhead;
    return $this;
  }

  public function addByline($byline) {
    $this->bylines[] = $byline;
    return $this;
  }

  protected function getTagName() {
    return 'span';
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-badge-view-css');
    $id = celerity_generate_unique_node_id();

    $classes = array();
    $classes[] = 'phui-badge-view';
    if ($this->quality) {
      $color = $this->getQualityColor();
      $classes[] = 'phui-badge-view-'.$color;
    }

    return array(
        'class' => implode(' ', $classes),
        'sigil' => 'jx-toggle-class',
        'id'    => $id,
        'meta'  => array(
          'map' => array(
            $id => 'card-flipped',
          ),
        ),
      );
  }

  protected function getTagContent() {

    $icon = id(new PHUIIconView())
      ->setIcon($this->icon);

    $illustration = phutil_tag_div('phui-badge-illustration', $icon);

    $header = null;
    if ($this->header) {
      $header = phutil_tag(
        ($this->href) ? 'a' : 'span',
        array(
          'class' => 'phui-badge-view-header',
          'href' => $this->href,
        ),
        $this->header);
    }

    $subhead = null;
    if ($this->subhead) {
      $subhead = phutil_tag_div('phui-badge-view-subhead', $this->subhead);
    }

    $information = phutil_tag(
      'div',
      array(
        'class' => 'phui-badge-view-information',
      ),
      array($header, $subhead));

    $quality = phutil_tag_div('phui-badge-quality', $this->getQualityName());
    $source = phutil_tag_div('phui-badge-source', $this->source);

    $bylines = array();
    if ($this->bylines) {
      foreach ($this->bylines as $byline) {
        $bylines[] = phutil_tag_div('phui-badge-byline', $byline);
      }
    }

    $card_front_1 = phutil_tag(
      'div',
      array(
        'class' => 'phui-badge-inner-front',
      ),
      array(
        $illustration,
      ));

    $card_front_2 = phutil_tag(
      'div',
      array(
        'class' => 'phui-badge-inner-front',
      ),
      array(
        $information,
      ));

    $back_info = phutil_tag(
      'div',
      array(
        'class' => 'phui-badge-view-information',
      ),
      array(
        $quality,
        $source,
        $bylines,
      ));

    $card_back = phutil_tag(
      'div',
      array(
        'class' => 'phui-badge-inner-back',
      ),
      array(
        $back_info,
      ));

    $inner_front = phutil_tag(
      'div',
      array(
        'class' =>  'phui-badge-front-view',
      ),
      array(
        $card_front_1,
        $card_front_2,
      ));

    $inner_back = phutil_tag_div('phui-badge-back-view', $card_back);
    $front = phutil_tag_div('phui-badge-card-front', $inner_front);
    $back = phutil_tag_div('phui-badge-card-back', $inner_back);

    $card = phutil_tag(
      'div',
      array(
        'class' => 'phui-badge-card',
      ),
      array(
        $front,
        $back,
      ));

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-badge-card-container',
      ),
      $card);

  }

}
