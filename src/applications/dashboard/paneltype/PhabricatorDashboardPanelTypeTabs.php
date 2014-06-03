<?php

final class PhabricatorDashboardPanelTypeTabs
  extends PhabricatorDashboardPanelType {

  public function getPanelTypeKey() {
    return 'tabs';
  }

  public function getPanelTypeName() {
    return pht('Tab Panel');
  }

  public function getPanelTypeDescription() {
    return pht(
      'Use tabs to switch between several other panels.');
  }

  public function getFieldSpecifications() {
    return array(
      'config' => array(
        'name' => pht('JSON Config'),
        'type' => 'remarkup',
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

    $config = phutil_json_decode($panel->getProperty('config'), null);
    if ($config === null) {
      throw new Exception(pht('The configuration is not valid JSON.'));
    }

    $list = id(new PHUIListView())
      ->setType(PHUIListView::NAVBAR_LIST);

    $selected = 0;

    // TODO: Instead of using reveal-content here, we should write some nice
    // JS which loads panels on demand, manages tab selected states, and maybe
    // saves the tab you selected.

    $node_ids = array();
    foreach ($config as $idx => $tab_spec) {
      $node_ids[$idx] = celerity_generate_unique_node_id();
    }

    Javelin::initBehavior('phabricator-reveal-content');

    foreach ($config as $idx => $tab_spec) {
      $hide_ids = $node_ids;
      unset($hide_ids[$idx]);

      $list->addMenuItem(
        id(new PHUIListItemView())
          ->setHref('#')
          ->addSigil('reveal-content')
          ->setMetadata(
            array(
              'showIDs' => array(idx($node_ids, $idx)),
              'hideIDs' => array_values($hide_ids),
            ))
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


    return array($list, $content);
  }

}
