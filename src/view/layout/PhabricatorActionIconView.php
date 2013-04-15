<?php

final class PhabricatorActionIconView extends AphrontView {

  const SPRITE_MINICONS = 'minicons';
  const SPRITE_ACTIONS = 'actions';
  const SPRITE_APPS = 'apps';

  private $href;
  private $workflow;
  private $image;
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

  public function setSpriteIcon($sprite) {
    $this->spriteIcon = $sprite;
    return $this;
  }

  public function setSpriteSheet($sheet) {
    $this->spriteSheet = $sheet;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-action-icon-view-css');

    $tag = 'span';
    if ($this->href) {
      $tag = 'a';
    }

    if ($this->spriteIcon) {
      require_celerity_resource('sprite-'.$this->spriteSheet.'-css');

      $classes = array();
      $classes[] = 'phabricator-action-icon-item-link';
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
      $action_icon = phutil_tag(
        $tag,
          array(
            'href'  => $this->href ? $this->href : null,
            'class' => 'phabricator-action-icon-item-link',
            'sigil' => $this->workflow ? 'workflow' : null,
            'style' => 'background-image: url('.$this->image.');'
          ),
          '');
    }

    return $action_icon;
  }
}
