<?php

final class PhabricatorPasteApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Paste');
  }

  public function getBaseURI() {
    return '/paste/';
  }

  public function getFontIcon() {
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
        'edit/(?P<id>[1-9]\d*)/' => 'PhabricatorPasteEditController',
        'comment/(?P<id>[1-9]\d*)/' => 'PhabricatorPasteCommentController',
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
      ),
      PasteDefaultEditCapability::CAPABILITY => array(
        'caption' => pht('Default edit policy for newly created pastes.'),
        'template' => PhabricatorPastePastePHIDType::TYPECONST,
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
