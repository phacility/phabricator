<?php

final class PHUIWorkpanelView extends AphrontTagView {

  private $cards = array();
  private $header;
  private $subheader = null;
  private $footerAction;
  private $headerColor = PHUIActionHeaderView::HEADER_GREY;
  private $headerActions = array();
  private $headerTag;
  private $headerIcon;

  public function setHeaderIcon(PHUIIconView $header_icon) {
    $this->headerIcon = $header_icon;
    return $this;
  }

  public function getHeaderIcon() {
    return $this->headerIcon;
  }

  public function setCards(PHUIObjectItemListView $cards) {
    $this->cards[] = $cards;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setSubheader($subheader) {
    $this->subheader = $subheader;
    return $this;
  }

  public function setFooterAction(PHUIListItemView $footer_action) {
    $this->footerAction = $footer_action;
    return $this;
  }

  public function setHeaderColor($header_color) {
    $this->headerColor = $header_color;
    return $this;
  }

  public function addHeaderAction(PHUIIconView $action) {
    $this->headerActions[] = $action;
    return $this;
  }

  public function setHeaderTag(PHUITagView $tag) {
    $this->headerTag = $tag;
    return $this;
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'phui-workpanel-view',
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phui-workpanel-view-css');

    $classes = array();
    $classes[] = 'phui-workpanel-view-inner';
    $footer = '';
    if ($this->footerAction) {
      $footer_tag = $this->footerAction;
      $footer = phutil_tag(
        'ul',
          array(
            'class' => 'phui-workpanel-footer-action mst ps',
          ),
          $footer_tag);
    }

    $header = id(new PHUIActionHeaderView())
      ->setHeaderTitle($this->header)
      ->setHeaderSubtitle($this->subheader)
      ->setHeaderColor($this->headerColor);

    if ($this->headerIcon) {
      $header->setHeaderIcon($this->headerIcon);
    }

    if ($this->headerTag) {
      $header->setTag($this->headerTag);
    }

    foreach ($this->headerActions as $action) {
      $header->addAction($action);
    }

    $classes[] = 'phui-workpanel-'.$this->headerColor;

    $body = phutil_tag(
      'div',
        array(
          'class' => 'phui-workpanel-body',
        ),
      $this->cards);

    $view = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      array(
        $header,
        $body,
        $footer,
      ));

    return $view;
  }
}
