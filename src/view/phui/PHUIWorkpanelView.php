<?php

final class PHUIWorkpanelView extends AphrontTagView {

  private $cards = array();
  private $header;
  private $subheader = null;
  private $footerAction;
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

    $header = id(new PHUIHeaderView())
      ->setHeader($this->header)
      ->setSubheader($this->subheader);

    if ($this->headerIcon) {
      $header->setHeaderIcon($this->headerIcon);
    }

    if ($this->headerTag) {
      $header->addTag($this->headerTag);
    }

    foreach ($this->headerActions as $action) {
      $header->addActionIcon($action);
    }

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
