<?php

final class PHUIWorkpanelView extends AphrontTagView {

  private $cards = array();
  private $header;
  private $subheader = null;
  private $footerAction;
  private $headerActions = array();
  private $headerTag;
  private $headerIcon;
  private $href;

  public function setHeaderIcon($icon) {
    $this->headerIcon = $icon;
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

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'phui-workpanel-view',
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phui-workpanel-view-css');

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

    foreach ($this->headerActions as $action) {
      $header->addActionItem($action);
    }

    if ($this->headerTag) {
      $header->addActionItem($this->headerTag);
    }

    if ($this->headerIcon) {
      $header->setHeaderIcon($this->headerIcon);
    }

    $href = $this->getHref();
    if ($href !== null) {
      $header->setHref($href);
    }

    $body = phutil_tag(
      'div',
        array(
          'class' => 'phui-workpanel-body-content',
        ),
      $this->cards);

    $body = phutil_tag_div('phui-workpanel-body', $body);

    $view = id(new PHUIBoxView())
      ->setColor(PHUIBoxView::GREY)
      ->addClass('phui-workpanel-view-inner')
      ->appendChild(
        array(
          $header,
          $body,
          $footer,
        ));

    return $view;
  }
}
