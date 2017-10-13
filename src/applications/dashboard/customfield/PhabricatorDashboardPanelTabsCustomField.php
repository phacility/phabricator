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
    $panel_ids = $request->getArr($this->getFieldKey().'_panelID');
    $panels = array();
    foreach ($panel_ids as $panel_id) {
      $panels[] = $panel_id[0];
    }
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

  public function getApplicationTransactionTitle(
    PhabricatorApplicationTransaction $xaction) {
    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    $new_tabs = array();
    if ($new) {
      foreach ($new as $new_tab) {
        $new_tabs[] = $new_tab['name'];
      }
      $new_tabs = implode(' | ', $new_tabs);
    }

    $old_tabs = array();
    if ($old) {
      foreach ($old as $old_tab) {
        $old_tabs[] = $old_tab['name'];
      }
      $old_tabs = implode(' | ', $old_tabs);
    }

    if (!$old) {
      // In case someone makes a tab panel with no tabs.
      if ($new) {
        return pht(
          '%s set the tabs to "%s".',
          $xaction->renderHandleLink($author_phid),
          $new_tabs);
      }
    } else if (!$new) {
      return pht(
        '%s removed tabs.',
        $xaction->renderHandleLink($author_phid));
    } else {
      return pht(
        '%s changed the tabs from "%s" to "%s".',
        $xaction->renderHandleLink($author_phid),
        $old_tabs,
        $new_tabs);
    }
  }

  public function renderEditControl(array $handles) {
    // NOTE: This includes archived panels so we don't mutate the tabs
    // when saving a tab panel that includes archived panels. This whole UI is
    // hopefully temporary anyway.

    $value = $this->getFieldValue();
    if (!is_array($value)) {
      $value = array();
    }

    $out = array();
    for ($ii = 1; $ii <= 6; $ii++) {
      $tab = idx($value, ($ii - 1), array());
      $panel = idx($tab, 'panelID', null);
      $panel_id = array();
      if ($panel) {
        $panel_id[] = $panel;
      }
      $out[] = id(new AphrontFormTextControl())
        ->setName($this->getFieldKey().'_name[]')
        ->setValue(idx($tab, 'name'))
        ->setLabel(pht('Tab %d Name', $ii));

      $out[] = id(new AphrontFormTokenizerControl())
        ->setUser($this->getViewer())
        ->setDatasource(new PhabricatorDashboardPanelDatasource())
        ->setName($this->getFieldKey().'_panelID[]')
        ->setValue($panel_id)
        ->setLimit(1)
        ->setLabel(pht('Tab %d Panel', $ii));
    }

    return $out;
  }

}
