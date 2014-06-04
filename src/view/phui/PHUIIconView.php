<?php

final class PHUIIconView extends AphrontTagView {

  const SPRITE_MINICONS = 'minicons';
  const SPRITE_APPS = 'apps';
  const SPRITE_TOKENS = 'tokens';
  const SPRITE_PAYMENTS = 'payments';
  const SPRITE_LOGIN = 'login';
  const SPRITE_PROJECTS = 'projects';

  const HEAD_SMALL = 'phuihead-small';
  const HEAD_MEDIUM = 'phuihead-medium';

  private $href = null;
  private $image;
  private $text;
  private $headSize = null;

  private $spriteIcon;
  private $spriteSheet;
  private $iconFont;

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setImage($image) {
    $this->image = $image;
    return $this;
  }

  public function setText($text) {
    $this->text = $text;
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

  public function setIconFont($icon) {
    $this->iconFont = $icon;
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

    $style = null;
    $classes = array();
    $classes[] = 'phui-icon-view';

    if ($this->spriteIcon) {
      require_celerity_resource('sprite-'.$this->spriteSheet.'-css');
      $classes[] = 'sprite-'.$this->spriteSheet;
      $classes[] = $this->spriteSheet.'-'.$this->spriteIcon;

    } elseif ($this->iconFont) {
      require_celerity_resource('phui-font-icon-base-css');
      require_celerity_resource('font-fontawesome');
      $classes[] = 'phui-font-fa';
      $classes[] = $this->iconFont;

    } else {
      if ($this->headSize) {
        $classes[] = $this->headSize;
      }
      $style = 'background-image: url('.$this->image.');';
    }

    if ($this->text) {
      $classes[] = 'phui-icon-has-text';
      $this->appendChild($this->text);
    }

    return array(
      'href' => $this->href,
      'style' => $style,
      'aural' => false,
      'class' => $classes,
    );
  }

  public static function getSheetManifest($sheet) {
    $root = dirname(phutil_get_library_root('phabricator'));
    $path = $root.'/resources/sprite/manifest/'.$sheet.'.json';
    $data = Filesystem::readFile($path);
    return idx(json_decode($data, true), 'sprites');
  }


}
