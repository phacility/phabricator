<?php

final class PHUIWorkpanelView extends AphrontTagView {

  private $cards = array();
  private $header;
  private $editURI;
  private $footerAction;
  private $headerColor = PhabricatorActionHeaderView::HEADER_GREY;

  public function setCards(PHUIObjectItemListView $cards) {
    $this->cards[] = $cards;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setEditURI($edit_uri) {
    $this->editURI = $edit_uri;
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

  public function getTagAttributes() {
    return array(
      'class' => 'phui-workpanel-view',
    );
  }

  public function getTagContent() {
    require_celerity_resource('phui-workpanel-view-css');

    $footer = '';
    if ($this->footerAction) {
      $footer_tag = $this->footerAction;
      $footer = phutil_tag(
        'ul',
          array(
            'class' => 'phui-workpanel-footer-action mst ps'
          ),
          $footer_tag);
    }

    $header_edit = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_ACTIONS)
      ->setSpriteIcon('settings-grey')
      ->setHref($this->editURI);

    $header = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle($this->header)
      ->setHeaderColor($this->headerColor)
      ->addAction($header_edit);

    $body = phutil_tag(
      'div',
        array(
          'class' => 'phui-workpanel-body'
        ),
      $this->cards);

    $view = phutil_tag(
      'div',
      array(
        'class' => 'phui-workpanel-view-inner',
      ),
      array(
        $header,
        $body,
        $footer,
      ));

    return $view;
  }
}
