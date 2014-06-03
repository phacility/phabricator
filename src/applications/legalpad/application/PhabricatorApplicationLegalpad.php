<?php

final class PhabricatorApplicationLegalpad extends PhabricatorApplication {

  public function getBaseURI() {
    return '/legalpad/';
  }

  public function getShortDescription() {
    return pht('Agreements and Signatures');
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
    return self::GROUP_UTILITIES;
  }

  public function isBeta() {
    return true;
  }

  public function getRemarkupRules() {
    return array(
      new LegalpadDocumentRemarkupRule(),
    );
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
        'verify/(?P<code>[^/]+)/' =>
        'LegalpadDocumentSignatureVerificationController',
        'signatures/(?P<id>\d+)/' => 'LegalpadDocumentSignatureListController',
        'document/' => array(
          'preview/' => 'PhabricatorMarkupPreviewController'),
      ));
  }

  protected function getCustomCapabilities() {
    return array(
      LegalpadCapabilityDefaultView::CAPABILITY => array(
      ),
      LegalpadCapabilityDefaultEdit::CAPABILITY => array(
      ),
    );
  }

}
