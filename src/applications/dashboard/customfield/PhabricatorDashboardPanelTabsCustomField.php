<?php

final class PhabricatorDashboardPanelTabsCustomField
  extends PhabricatorStandardCustomField {

  public function getFieldType() {
    return 'dashboard.tabs';
  }

  public function shouldAppearInApplicationSearch() {
    return false;
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $value = array();

    $names = $request->getArr($this->getFieldKey().'_name');
    $panels = $request->getArr($this->getFieldKey().'_panelID');
    foreach ($names as $idx => $name) {
      $panel_id = idx($panels, $idx);
      if (strlen($name) && $panel_id) {
        $value[] = array(
          'name' => $name,
          'panelID' => $panel_id,
        );
      }
    }

    $this->setFieldValue($value);
  }

  public function renderEditControl(array $handles) {
    // NOTE: This includes archived panels so we don't mutate the tabs
    // when saving a tab panel that includes archied panels. This whole UI is
    // hopefully temporary anyway.

    $panels = id(new PhabricatorDashboardPanelQuery())
      ->setViewer($this->getViewer())
      ->execute();

    $panel_map = array();
    foreach ($panels as $panel) {
      $panel_map[$panel->getID()] = pht(
        '%s %s',
        $panel->getMonogram(),
        $panel->getName());
    }
    $panel_map = array(
      '' => pht('(None)'),
    ) + $panel_map;

    $value = $this->getFieldValue();
    if (!is_array($value)) {
      $value = array();
    }

    $out = array();
    for ($ii = 1; $ii <= 6; $ii++) {
      $tab = idx($value, ($ii - 1), array());
      $out[] = id(new AphrontFormTextControl())
        ->setName($this->getFieldKey().'_name[]')
        ->setValue(idx($tab, 'name'))
        ->setLabel(pht('Tab %d Name', $ii));

      $out[] = id(new AphrontFormSelectControl())
        ->setName($this->getFieldKey().'_panelID[]')
        ->setValue(idx($tab, 'panelID'))
        ->setOptions($panel_map)
        ->setLabel(pht('Tab %d Panel', $ii));
    }

    return $out;
  }

}
