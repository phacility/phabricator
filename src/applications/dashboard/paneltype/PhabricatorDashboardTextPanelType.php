<?php

final class PhabricatorDashboardTextPanelType
  extends PhabricatorDashboardPanelType {

  public function getPanelTypeKey() {
    return 'text';
  }

  public function getPanelTypeName() {
    return pht('Text Panel');
  }

  public function getPanelTypeDescription() {
    return pht(
      'Add some static text to the dashboard. This can be used to '.
      'provide instructions or context.');
  }

  public function getFieldSpecifications() {
    return array(
      'text' => array(
        'name' => pht('Text'),
        'type' => 'remarkup',
      ),
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

    $text_content = PhabricatorMarkupEngine::renderOneObject(
      id(new PhabricatorMarkupOneOff())->setContent($text),
      'default',
      $viewer);

    return id(new PHUIPropertyListView())
      ->addTextContent($text_content);
  }

}
