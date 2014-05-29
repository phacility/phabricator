<?php

final class PhabricatorApplicationPholio extends PhabricatorApplication {

  public function getBaseURI() {
    return '/pholio/';
  }

  public function getShortDescription() {
    return pht('Review Mocks and Design');
  }

  public function getIconName() {
    return 'pholio';
  }

  public function getTitleGlyph() {
    return "\xE2\x9D\xA6";
  }

  public function getFlavorText() {
    return pht('Things before they were cool.');
  }

  public function isBeta() {
    return true;
  }

  public function getEventListeners() {
    return array(
      new PholioActionMenuEventListener(),
    );
  }

  public function getRemarkupRules() {
    return array(
      new PholioRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/M(?P<id>[1-9]\d*)(?:/(?P<imageID>\d+)/)?' => 'PholioMockViewController',
      '/pholio/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PholioMockListController',
        'new/'                  => 'PholioMockEditController',
        'edit/(?P<id>\d+)/'     => 'PholioMockEditController',
        'comment/(?P<id>\d+)/'  => 'PholioMockCommentController',
        'inline/' => array(
          '(?P<id>\d+)/' => 'PholioInlineController',
          'save/' => 'PholioInlineSaveController',
          'delete/(?P<id>\d+)/' => 'PholioInlineDeleteController',
          'view/(?P<id>\d+)/' => 'PholioInlineViewController',
          'edit/(?P<id>\d+)/' => 'PholioInlineEditController',
          'thumb/(?P<imageid>\d+)/' => 'PholioInlineThumbController'
        ),
        'image/' => array(
          'upload/' => 'PholioImageUploadController',
          'history/(?P<id>\d+)/' => 'PholioImageHistoryController',
        ),
      ),
    );
  }

  public function getQuickCreateItems(PhabricatorUser $viewer) {
    $items = array();

    $item = id(new PHUIListItemView())
      ->setName(pht('Pholio Mock'))
      ->setIcon('fa-picture-o')
      ->setHref($this->getBaseURI().'new/');
    $items[] = $item;

    return $items;
  }

  protected function getCustomCapabilities() {
    return array(
      PholioCapabilityDefaultView::CAPABILITY => array(
      ),
    );
  }

}
