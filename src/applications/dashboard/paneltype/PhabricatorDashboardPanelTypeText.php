<?php

final class PhabricatorDashboardPanelTypeText
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

  protected function renderPanelContent(
    PhabricatorUser $viewer,
    PhabricatorDashboardPanel $panel) {

    $text = $panel->getProperty('text', '');

    $text_content = PhabricatorMarkupEngine::renderOneObject(
      id(new PhabricatorMarkupOneOff())->setContent($text),
      'default',
      $viewer);

    return id(new PHUIPropertyListView())
      ->addTextContent($text_content);
  }

}
