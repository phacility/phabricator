<?php

final class PhabricatorDashboardTextPanelType
  extends PhabricatorDashboardPanelType {

  public function getPanelTypeKey() {
    return 'text';
  }

  public function getPanelTypeName() {
    return pht('Text Panel');
  }

  public function getIcon() {
    return 'fa-file-text-o';
  }

  public function getPanelTypeDescription() {
    return pht(
      'Add a text panel to the dashboard to provide instructions or '.
      'context.');
  }

  protected function newEditEngineFields(PhabricatorDashboardPanel $panel) {
    return array(
      id(new PhabricatorRemarkupEditField())
        ->setKey('text')
        ->setLabel(pht('Text'))
        ->setTransactionType(
          PhabricatorDashboardTextPanelTextTransaction::TRANSACTIONTYPE)
        ->setValue($panel->getProperty('text', '')),
    );
  }

  public function shouldRenderAsync() {
    // Rendering text panels is normally a cheap cache hit.
    return false;
  }

  public function renderPanelContent(
    PhabricatorUser $viewer,
    PhabricatorDashboardPanel $panel,
    PhabricatorDashboardPanelRenderingEngine $engine) {

    $text = $panel->getProperty('text', '');
    $oneoff = id(new PhabricatorMarkupOneOff())->setContent($text);
    $field = 'default';

    // NOTE: We're taking extra steps here to prevent creation of a text panel
    // which embeds itself using `{Wnnn}`, recursing indefinitely.

    $parent_key = PhabricatorDashboardRemarkupRule::KEY_PARENT_PANEL_PHIDS;
    $parent_phids = $engine->getParentPanelPHIDs();
    $parent_phids[] = $panel->getPHID();

    $markup_engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer)
      ->setContextObject($panel)
      ->setAuxiliaryConfig($parent_key, $parent_phids);

    $text_content = $markup_engine
      ->addObject($oneoff, $field)
      ->process()
      ->getOutput($oneoff, $field);

    return id(new PHUIPropertyListView())
      ->addTextContent($text_content);
  }

}
