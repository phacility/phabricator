<?php

final class PhabricatorPholioApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Pholio');
  }

  public function getBaseURI() {
    return '/pholio/';
  }

  public function getShortDescription() {
    return pht('Review Mocks and Design');
  }

  public function getFontIcon() {
    return 'fa-camera-retro';
  }

  public function getTitleGlyph() {
    return "\xE2\x9D\xA6";
  }

  public function getFlavorText() {
    return pht('Things before they were cool.');
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
          '(?:(?P<id>\d+)/)?' => 'PholioInlineController',
          'list/(?P<id>\d+)/' => 'PholioInlineListController',
        ),
        'image/' => array(
          'upload/' => 'PholioImageUploadController',
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
      PholioDefaultViewCapability::CAPABILITY => array(
        'template' => PholioMockPHIDType::TYPECONST,
      ),
      PholioDefaultEditCapability::CAPABILITY => array(
        'template' => PholioMockPHIDType::TYPECONST,
      ),
    );
  }

  public function getMailCommandObjects() {
    return array(
      'mock' => array(
        'name' => pht('Email Commands: Mocks'),
        'header' => pht('Interacting with Pholio Mocks'),
        'object' => new PholioMock(),
        'summary' => pht(
          'This page documents the commands you can use to interact with '.
          'mocks in Pholio.'),
      ),
    );
  }

  public function getApplicationSearchDocumentTypes() {
    return array(
      PholioMockPHIDType::TYPECONST,
    );
  }

}
