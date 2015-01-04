<?php

final class PhabricatorDashboardTabsPanelType
  extends PhabricatorDashboardPanelType {

  public function getPanelTypeKey() {
    return 'tabs';
  }

  public function getPanelTypeName() {
    return pht('Tab Panel');
  }

  public function getPanelTypeDescription() {
    return pht('Use tabs to switch between several other panels.');
  }

  public function getFieldSpecifications() {
    return array(
      'config' => array(
        'name' => pht('Tabs'),
        'type' => 'dashboard.tabs',
      ),
    );
  }

  public function shouldRenderAsync() {
    // The actual tab panel itself is cheap to render.
    return false;
  }

  public function renderPanelContent(
    PhabricatorUser $viewer,
    PhabricatorDashboardPanel $panel,
    PhabricatorDashboardPanelRenderingEngine $engine) {

    $config = $panel->getProperty('config');
    if (!is_array($config)) {
      // NOTE: The older version of this panel stored raw JSON.
      $config = phutil_json_decode($config);
    }

    $list = id(new PHUIListView())
      ->setType(PHUIListView::NAVBAR_LIST);

    $selected = 0;

    $node_ids = array();
    foreach ($config as $idx => $tab_spec) {
      $node_ids[$idx] = celerity_generate_unique_node_id();
    }

    foreach ($config as $idx => $tab_spec) {
      $list->addMenuItem(
        id(new PHUIListItemView())
          ->setHref('#')
          ->setSelected($idx == $selected)
          ->addSigil('dashboard-tab-panel-tab')
          ->setMetadata(array('idx' => $idx))
          ->setName(idx($tab_spec, 'name', pht('Nameless Tab'))));
    }

    $ids = ipull($config, 'panelID');
    if ($ids) {
      $panels = id(new PhabricatorDashboardPanelQuery())
        ->setViewer($viewer)
        ->withIDs($ids)
        ->execute();
    } else {
      $panels = array();
    }

    $parent_phids = $engine->getParentPanelPHIDs();
    $parent_phids[] = $panel->getPHID();

    // TODO: Currently, we'll load all the panels on page load. It would be
    // vaguely nice to load hidden panels only when the user selects them.

    // TODO: Maybe we should persist which panel the user selected, so it
    // remains selected across page loads.

    $content = array();
    $no_headers = PhabricatorDashboardPanelRenderingEngine::HEADER_MODE_NONE;
    foreach ($config as $idx => $tab_spec) {
      $panel_id = idx($tab_spec, 'panelID');
      $panel = idx($panels, $panel_id);

      if ($panel) {
        $panel_content = id(new PhabricatorDashboardPanelRenderingEngine())
          ->setViewer($viewer)
          ->setEnableAsyncRendering(true)
          ->setParentPanelPHIDs($parent_phids)
          ->setPanel($panel)
          ->setHeaderMode($no_headers)
          ->renderPanel();
      } else {
        $panel_content = pht('(Invalid Panel)');
      }

      $content[] = phutil_tag(
        'div',
        array(
          'id' => $node_ids[$idx],
          'style' => ($idx == $selected) ? null : 'display: none',
        ),
        $panel_content);
    }

    Javelin::initBehavior('dashboard-tab-panel');

    return javelin_tag(
      'div',
      array(
        'sigil' => 'dashboard-tab-panel-container',
        'meta' => array(
          'panels' => $node_ids,
        ),
      ),
      array(
        $list,
        $content,
      ));
  }

}
