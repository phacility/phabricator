<?php

final class PhabricatorWorkpanelView extends AphrontView {

  private $cards = array();
  private $header;
  private $headerAction;
  private $footerAction;

  public function setCards(PhabricatorObjectItemListView $cards) {
    $this->cards[] = $cards;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setHeaderAction($header_action) {
    $this->headerAction = $header_action;
    return $this;
  }

  public function setFooterAction(PhabricatorMenuItemView $footer_action) {
    $this->footerAction = $footer_action;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-workpanel-view-css');

    $footer = '';
    if ($this->footerAction) {
      $footer_tag = $this->footerAction;
      $footer = phutil_tag(
        'div',
          array(
            'class' => 'phabricator-workpanel-footer-action mst ps'
          ),
          $footer_tag);
    }

    $header = id(new PhabricatorActionHeaderView())
      ->setHeaderTitle($this->header)
      ->setHeaderColor(PhabricatorActionHeaderView::HEADER_GREY);

    $body = phutil_tag(
      'div',
        array(
          'class' => 'phabricator-workpanel-body'
        ),
      $this->cards);

    $view = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-workpanel-view-inner',
      ),
      array(
        $header,
        $body,
        $footer,
      ));

    return phutil_tag(
      'div',
        array(
          'class' => 'phabricator-workpanel-view'
        ),
        $view);
  }
}
