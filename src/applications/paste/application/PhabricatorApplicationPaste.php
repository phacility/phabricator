<?php

final class PhabricatorApplicationPaste extends PhabricatorApplication {

  public function getBaseURI() {
    return '/paste/';
  }

  public function getIconName() {
    return 'paste';
  }

  public function getTitleGlyph() {
    return "\xE2\x9C\x8E";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getShortDescription() {
    return pht('Share Text Snippets');
  }

  public function getRemarkupRules() {
    return array(
      new PhabricatorPasteRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/P(?P<id>[1-9]\d*)(?:\$(?P<lines>\d+(?:-\d+)?))?'
        => 'PhabricatorPasteViewController',
      '/paste/' => array(
        '(query/(?P<queryKey>[^/]+)/)?' => 'PhabricatorPasteListController',
        'create/'                       => 'PhabricatorPasteEditController',
        'edit/(?P<id>[1-9]\d*)/'        => 'PhabricatorPasteEditController',
        'comment/(?P<id>[1-9]\d*)/'     => 'PhabricatorPasteCommentController',
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      PasteCapabilityDefaultView::CAPABILITY => array(
        'caption' => pht(
          'Default view policy for newly created pastes.')
      ),
    );
  }

  public function getQuickCreateItems(PhabricatorUser $viewer) {
    $items = array();

    $item = id(new PHUIListItemView())
      ->setName(pht('Paste'))
      ->setIcon('fa-clipboard')
      ->setHref($this->getBaseURI().'create/');
    $items[] = $item;

    return $items;
  }

}
