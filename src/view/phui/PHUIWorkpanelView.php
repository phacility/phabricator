<?php

final class PHUIWorkpanelView extends AphrontView {

  private $cards = array();
  private $header;
  private $headerAction;
  private $footerAction;

  public function setCards(PHUIObjectItemListView $cards) {
    $this->cards[] = $cards;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setHeaderAction($header_action) {
    // TODO: This doesn't do anything?
    $this->headerAction = $header_action;
    return $this;
  }

  public function setFooterAction(PHUIListItemView $footer_action) {
    $this->footerAction = $footer_action;
    return $this;
  }

  public function render() {
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

    $header = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle($this->header)
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_GREY);

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

    return phutil_tag(
      'div',
        array(
          'class' => 'phui-workpanel-view'
        ),
        $view);
  }
}
