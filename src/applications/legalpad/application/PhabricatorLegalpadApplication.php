<?php

final class PhabricatorLegalpadApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/legalpad/';
  }

  public function getName() {
    return pht('Legalpad');
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

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getRemarkupRules() {
    return array(
      new LegalpadDocumentRemarkupRule(),
    );
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('Legalpad User Guide');
  }

  public function getOverview() {
    return pht(
      '**Legalpad** is a simple application for tracking signatures and '.
      'legal agreements. At the moment, it is primarily intended to help '.
      'open source projects keep track of Contributor License Agreements.');
  }

  public function getRoutes() {
    return array(
      '/L(?P<id>\d+)' => 'LegalpadDocumentSignController',
      '/legalpad/' => array(
        '' => 'LegalpadDocumentListController',
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'LegalpadDocumentListController',
        'create/' => 'LegalpadDocumentEditController',
        'edit/(?P<id>\d+)/' => 'LegalpadDocumentEditController',
        'comment/(?P<id>\d+)/' => 'LegalpadDocumentCommentController',
        'view/(?P<id>\d+)/' => 'LegalpadDocumentManageController',
        'done/' => 'LegalpadDocumentDoneController',
        'verify/(?P<code>[^/]+)/'
          => 'LegalpadDocumentSignatureVerificationController',
        'signatures/(?:(?P<id>\d+)/)?(?:query/(?P<queryKey>[^/]+)/)?'
          => 'LegalpadDocumentSignatureListController',
        'addsignature/(?P<id>\d+)/' => 'LegalpadDocumentSignatureAddController',
        'signature/(?P<id>\d+)/' => 'LegalpadDocumentSignatureViewController',
        'document/' => array(
          'preview/' => 'PhabricatorMarkupPreviewController',
        ),
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      LegalpadCreateDocumentsCapability::CAPABILITY => array(),
      LegalpadDefaultViewCapability::CAPABILITY => array(),
      LegalpadDefaultEditCapability::CAPABILITY => array(),
    );
  }

}
