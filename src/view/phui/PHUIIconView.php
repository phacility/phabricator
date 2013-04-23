<?php

final class PHUIIconView extends AphrontTagView {

  const SPRITE_MINICONS = 'minicons';
  const SPRITE_ACTIONS = 'actions';
  const SPRITE_APPS = 'apps';
  const SPRITE_TOKENS = 'tokens';
  const SPRITE_PAYMENTS = 'payments';

  const HEAD_SMALL = 'phuihead-small';
  const HEAD_MEDIUM = 'phuihead-medium';

  private $href = null;
  private $image;
  private $headSize = null;
  private $spriteIcon;
  private $spriteSheet;

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setImage($image) {
    $this->image = $image;
    return $this;
  }

  public function setHeadSize($size) {
    $this->headSize = $size;
    return $this;
  }

  public function setSpriteIcon($sprite) {
    $this->spriteIcon = $sprite;
    return $this;
  }

  public function setSpriteSheet($sheet) {
    $this->spriteSheet = $sheet;
    return $this;
  }

  public function getTagName() {
    $tag = 'span';
    if ($this->href) {
      $tag = 'a';
    }
    return $tag;
  }

  public function getTagAttributes() {
    require_celerity_resource('phui-icon-view-css');

    $this->addClass('phui-icon-item-link');

    if ($this->spriteIcon) {
      require_celerity_resource('sprite-'.$this->spriteSheet.'-css');
      $this->addClass('sprite-'.$this->spriteSheet);
      $this->addClass($this->spriteSheet.'-'.$this->spriteIcon);
    } else {
      if ($this->headSize) {
        $this->addClass($this->headSize);
      }
      $this->setStyle('background-image: url('.$this->image.');');
    }

    $attribs = array('href' => $this->href);
    return $attribs;
  }
}
