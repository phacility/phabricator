<?php

/**
 * @group legalpad
 */
final class PhabricatorApplicationLegalpad extends PhabricatorApplication {

  public function getBaseURI() {
    return '/legalpad/';
  }

  public function getShortDescription() {
    return pht('Legal Documents');
  }

  public function getIconName() {
    return 'legalpad';
  }

  public function getTitleGlyph() {
    return "\xC2\xA9";
  }

  public function getFlavorText() {
    return pht('With advanced signature technology.');
  }

  public function getApplicationGroup() {
    return self::GROUP_COMMUNICATION;
  }

  public function getQuickCreateURI() {
    return $this->getBaseURI().'create/';
  }


  public function isBeta() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/L(?P<id>\d+)' => 'LegalpadDocumentSignController',
      '/legalpad/' => array(
        '' => 'LegalpadDocumentListController',
        '(query/(?P<queryKey>[^/]+)/)?' => 'LegalpadDocumentListController',
        'create/' => 'LegalpadDocumentEditController',
        'edit/(?P<id>\d+)/' => 'LegalpadDocumentEditController',
        'comment/(?P<id>\d+)/' => 'LegalpadDocumentCommentController',
        'view/(?P<id>\d+)/' => 'LegalpadDocumentViewController',
        'document/' => array(
          'preview/' => 'PhabricatorMarkupPreviewController'),
      ));
  }

}
