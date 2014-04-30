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

}
