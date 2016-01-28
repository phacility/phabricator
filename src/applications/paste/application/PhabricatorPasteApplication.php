<?php

final class PhabricatorPasteApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Paste');
  }

  public function getBaseURI() {
    return '/paste/';
  }

  public function getIcon() {
    return 'fa-paste';
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
        'create/' => 'PhabricatorPasteEditController',
        $this->getEditRoutePattern('edit/') => 'PhabricatorPasteEditController',
        'raw/(?P<id>[1-9]\d*)/' => 'PhabricatorPasteRawController',
        'archive/(?P<id>[1-9]\d*)/' => 'PhabricatorPasteArchiveController',
      ),
    );
  }

  public function supportsEmailIntegration() {
    return true;
  }

  public function getAppEmailBlurb() {
    return pht(
      'Send email to these addresses to create pastes. %s',
      phutil_tag(
        'a',
        array(
          'href' => $this->getInboundEmailSupportLink(),
        ),
        pht('Learn More')));
  }

  protected function getCustomCapabilities() {
    return array(
      PasteDefaultViewCapability::CAPABILITY => array(
        'caption' => pht('Default view policy for newly created pastes.'),
        'template' => PhabricatorPastePastePHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
      PasteDefaultEditCapability::CAPABILITY => array(
        'caption' => pht('Default edit policy for newly created pastes.'),
        'template' => PhabricatorPastePastePHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
      ),
    );
  }

  public function getQuickCreateItems(PhabricatorUser $viewer) {
    return id(new PhabricatorPasteEditEngine())
      ->setViewer($viewer)
      ->loadQuickCreateItems();
  }

  public function getMailCommandObjects() {
    return array(
      'paste' => array(
        'name' => pht('Email Commands: Pastes'),
        'header' => pht('Interacting with Pastes'),
        'object' => new PhabricatorPaste(),
        'summary' => pht(
          'This page documents the commands you can use to interact with '.
          'pastes.'),
      ),
    );
  }

}
