<?php

final class PHUIActionPanelView extends AphrontTagView {

  private $href;
  private $fontIcon;
  private $header;
  private $subHeader;
  private $bigText;
  private $state;
  private $status;

  const STATE_WARN = 'phui-action-panel-warn';
  const STATE_INFO = 'phui-action-panel-info';
  const STATE_ERROR = 'phui-action-panel-error';
  const STATE_SUCCESS = 'phui-action-panel-success';
  const STATE_PROGRESS = 'phui-action-panel-progress';
  const STATE_NONE = 'phui-action-panel-none';

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setFontIcon($image) {
    $this->fontIcon = $image;
    return $this;
  }

  public function setBigText($text) {
    $this->bigText = $text;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setSubHeader($sub) {
    $this->subHeader = $sub;
    return $this;
  }

  public function setState($state) {
    $this->state = $state;
    return $this;
  }

  public function setStatus($text) {
    $this->status = $text;
    return $this;
  }

  protected function getStateIcon() {
    $icon = new PHUIIconView();
    switch ($this->state) {
      case self::STATE_WARN:
        $icon->setIconFont('fa-exclamation-circle msr');
      break;
      case self::STATE_INFO:
        $icon->setIconFont('fa-info-circle msr');
      break;
      case self::STATE_ERROR:
        $icon->setIconFont('fa-exclamation-triangle msr');
      break;
      case self::STATE_PROGRESS:
        $icon->setIconFont('fa-refresh ph-spin msr');
      break;
      case self::STATE_SUCCESS:
        $icon->setIconFont('fa-check msr');
      break;
      case self::STATE_NONE:
        return null;
      break;
    }
    return $icon;
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-action-panel-css');

    $classes = array();
    $classes[] = 'phui-action-panel';
    if ($this->status) {
      $classes[] = 'phui-action-panel-has-status';
      $classes[] = $this->state;

    }

    return array(
      'class' => implode(' ', $classes),
    );

  }

  protected function getTagContent() {

    $icon = null;
    if ($this->fontIcon || $this->bigText) {
      if ($this->fontIcon) {
        $fonticon = id(new PHUIIconView())
          ->setIconFont($this->fontIcon);
      } else {
        $fonticon = phutil_tag(
          'span',
          array(
            'class' => 'phui-action-panel-bigtext',
          ),
          $this->bigText);
      }
      if ($this->href) {
        $fonticon = phutil_tag(
          'a',
          array(
            'href' => $this->href,
          ),
          $fonticon);
      }
      $icon = phutil_tag(
        'div',
        array(
          'class' => 'phui-action-panel-icon',
        ),
        $fonticon);
    }

    $header = null;
    if ($this->header) {
      $header = $this->header;
      if ($this->href) {
        $header = phutil_tag(
          'a',
          array(
            'href' => $this->href,
          ),
          $this->header);
      }
      $header = phutil_tag(
        'div',
        array(
          'class' => 'phui-action-panel-header',
        ),
        $header);
    }

    $subheader = null;
    if ($this->subHeader) {
      $subheader = phutil_tag(
        'div',
        array(
          'class' => 'phui-action-panel-subheader',
        ),
        $this->subHeader);
    }

    $status = null;
    if ($this->status && $this->state) {
      $state_icon = $this->getStateIcon();
      $status = phutil_tag(
        ($this->href) ? 'a' : 'div',
        array(
          'class' => 'phui-action-panel-status',
          'href' => ($this->href) ? $this->href : null,
        ),
        array($state_icon, $this->status));
    }

    return array($icon, $header, $subheader, $status);

  }

}
