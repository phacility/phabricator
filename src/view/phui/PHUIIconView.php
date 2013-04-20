<?php

final class PHUIIconView extends AphrontView {

  const SPRITE_MINICONS = 'minicons';
  const SPRITE_ACTIONS = 'actions';
  const SPRITE_APPS = 'apps';
  const SPRITE_TOKENS = 'tokens';

  const HEAD_SMALL = 'phuihead-small';
  const HEAD_MEDIUM = 'phuihead-medium';

  private $href;
  private $workflow;
  private $image;
  private $headSize = null;
  private $spriteIcon;
  private $spriteSheet;

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
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

  public function render() {
    require_celerity_resource('phui-icon-view-css');

    $tag = 'span';
    if ($this->href) {
      $tag = 'a';
    }

    $classes = array();
    $classes[] = 'phui-icon-item-link';

    if ($this->spriteIcon) {
      require_celerity_resource('sprite-'.$this->spriteSheet.'-css');
      $classes[] = 'sprite-'.$this->spriteSheet;
      $classes[] = $this->spriteSheet.'-'.$this->spriteIcon;

      $action_icon = phutil_tag(
        $tag,
          array(
            'href'  => $this->href ? $this->href : null,
            'class' => implode(' ', $classes),
            'sigil' => $this->workflow ? 'workflow' : null,
          ),
          '');
    } else {
      if ($this->headSize) {
        $classes[] = $this->headSize;
      }

      $action_icon = phutil_tag(
        $tag,
          array(
            'href'  => $this->href ? $this->href : null,
            'class' => implode(' ', $classes),
            'sigil' => $this->workflow ? 'workflow' : null,
            'style' => 'background-image: url('.$this->image.');'
          ),
          '');
    }

    return $action_icon;
  }
}
