<?php

final class PhabricatorDashboardTabsPanelType
  extends PhabricatorDashboardPanelType {

  public function getPanelTypeKey() {
    return 'tabs';
  }

  public function getPanelTypeName() {
    return pht('Tab Panel');
  }

  public function getIcon() {
    return 'fa-columns';
  }

  public function getPanelTypeDescription() {
    return pht('Use tabs to switch between several other panels.');
  }

  protected function newEditEngineFields(PhabricatorDashboardPanel $panel) {
    return array();
  }

  public function shouldRenderAsync() {
    // The actual tab panel itself is cheap to render.
    return false;
  }

  public function getPanelConfiguration(PhabricatorDashboardPanel $panel) {
    $config = $panel->getProperty('config');

    if (!is_array($config)) {
      // NOTE: The older version of this panel stored raw JSON.
      try {
        $config = phutil_json_decode($config);
      } catch (PhutilJSONParserException $ex) {
        $config = array();
      }
    }

    return $config;
  }

  public function renderPanelContent(
    PhabricatorUser $viewer,
    PhabricatorDashboardPanel $panel,
    PhabricatorDashboardPanelRenderingEngine $engine) {

    $is_edit = $engine->isEditMode();
    $config = $this->getPanelConfiguration($panel);

    $context_object = $engine->getContextObject();
    if (!$context_object) {
      $context_object = $panel;
    }

    $context_phid = $context_object->getPHID();

    $list = id(new PHUIListView())
      ->setType(PHUIListView::NAVBAR_LIST);

    $ids = ipull($config, 'panelID');
    if ($ids) {
      $panels = id(new PhabricatorDashboardPanelQuery())
        ->setViewer($viewer)
        ->withIDs($ids)
        ->execute();
    } else {
      $panels = array();
    }

    $id = $panel->getID();

    $add_uri = urisprintf('/dashboard/panel/tabs/%d/add/', $id);
    $add_uri = id(new PhutilURI($add_uri))
      ->replaceQueryParam('contextPHID', $context_phid);

    $remove_uri = urisprintf('/dashboard/panel/tabs/%d/remove/', $id);
    $remove_uri = id(new PhutilURI($remove_uri))
      ->replaceQueryParam('contextPHID', $context_phid);

    $rename_uri = urisprintf('/dashboard/panel/tabs/%d/rename/', $id);
    $rename_uri = id(new PhutilURI($rename_uri))
      ->replaceQueryParam('contextPHID', $context_phid);

    $selected = 0;

    $key_list = array_keys($config);

    $next_keys = array();
    $prev_keys = array();
    for ($ii = 0; $ii < count($key_list); $ii++) {
      $next_keys[$key_list[$ii]] = idx($key_list, $ii + 1);
      $prev_keys[$key_list[$ii]] = idx($key_list, $ii - 1);
    }

    foreach ($config as $idx => $tab_spec) {
      $panel_id = idx($tab_spec, 'panelID');
      $subpanel = idx($panels, $panel_id);

      $name = idx($tab_spec, 'name');
      if ($name === null || !strlen($name)) {
        if ($subpanel) {
          $name = $subpanel->getName();
        }
      }

      if (!strlen($name)) {
        $name = pht('Unnamed Tab');
      }

      $tab_view = id(new PHUIListItemView())
        ->setHref('#')
        ->setSelected((string)$idx === (string)$selected)
        ->addSigil('dashboard-tab-panel-tab')
        ->setMetadata(array('panelKey' => $idx))
        ->setName($name);

      if ($is_edit) {
        $dropdown_menu = id(new PhabricatorActionListView())
          ->setViewer($viewer);

        $remove_tab_uri = id(clone $remove_uri)
          ->replaceQueryParam('target', $idx);

        $rename_tab_uri = id(clone $rename_uri)
          ->replaceQueryParam('target', $idx);

        if ($subpanel) {
          $details_uri = $subpanel->getURI();
        } else {
          $details_uri = null;
        }

        $edit_uri = urisprintf(
          '/dashboard/panel/edit/%d/',
          $panel_id);
        if ($subpanel) {
          $can_edit = PhabricatorPolicyFilter::hasCapability(
            $viewer,
            $subpanel,
            PhabricatorPolicyCapability::CAN_EDIT);
        } else {
          $can_edit = false;
        }

        $dropdown_menu->addAction(
          id(new PhabricatorActionView())
            ->setName(pht('Rename Tab'))
            ->setIcon('fa-pencil')
            ->setHref($rename_tab_uri)
            ->setWorkflow(true));

        $move_uri = urisprintf('/dashboard/panel/tabs/%d/move/', $id);

        $prev_key = $prev_keys[$idx];
        $prev_params = array(
          'target' => $idx,
          'after' => $prev_key,
          'move' => 'prev',
          'contextPHID' => $context_phid,
        );
        $prev_uri = new PhutilURI($move_uri, $prev_params);

        $next_key = $next_keys[$idx];
        $next_params = array(
          'target' => $idx,
          'after' => $next_key,
          'move' => 'next',
          'contextPHID' => $context_phid,
        );
        $next_uri = new PhutilURI($move_uri, $next_params);

        $dropdown_menu->addAction(
          id(new PhabricatorActionView())
            ->setName(pht('Move Tab Left'))
            ->setIcon('fa-chevron-left')
            ->setHref($prev_uri)
            ->setWorkflow(true)
            ->setDisabled(($prev_key === null) || !$can_edit));

        $dropdown_menu->addAction(
          id(new PhabricatorActionView())
            ->setName(pht('Move Tab Right'))
            ->setIcon('fa-chevron-right')
            ->setHref($next_uri)
            ->setWorkflow(true)
            ->setDisabled(($next_key === null) || !$can_edit));

        $dropdown_menu->addAction(
          id(new PhabricatorActionView())
            ->setName(pht('Remove Tab'))
            ->setIcon('fa-times')
            ->setHref($remove_tab_uri)
            ->setWorkflow(true));

        $dropdown_menu->addAction(
          id(new PhabricatorActionView())
            ->setType(PhabricatorActionView::TYPE_DIVIDER));

        $dropdown_menu->addAction(
          id(new PhabricatorActionView())
            ->setName(pht('Edit Panel'))
            ->setIcon('fa-pencil')
            ->setHref($edit_uri)
            ->setWorkflow(true)
            ->setDisabled(!$can_edit));

        $dropdown_menu->addAction(
          id(new PhabricatorActionView())
            ->setName(pht('View Panel Details'))
            ->setIcon('fa-window-maximize')
            ->setHref($details_uri)
            ->setDisabled(!$subpanel));

        $tab_view
          ->setActionIcon('fa-caret-down', '#')
          ->setDropdownMenu($dropdown_menu);
      }

      $list->addMenuItem($tab_view);
    }


    if ($is_edit) {
      $actions = id(new PhabricatorActionListView())
        ->setViewer($viewer);

      $add_last_uri = clone $add_uri;

      $last_idx = last_key($config);
      if ($last_idx) {
        $add_last_uri->replaceQueryParam('after', $last_idx);
      }

      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Add Existing Panel'))
          ->setIcon('fa-window-maximize')
          ->setHref($add_last_uri)
          ->setWorkflow(true));

      $list->addMenuItem(
        id(new PHUIListItemView())
          ->setHref('#')
          ->setSelected(false)
          ->setName(pht('Add Tab...'))
          ->setDropdownMenu($actions));
    }

    $parent_phids = $engine->getParentPanelPHIDs();
    $parent_phids[] = $panel->getPHID();

    // TODO: Currently, we'll load all the panels on page load. It would be
    // vaguely nice to load hidden panels only when the user selects them.

    // TODO: Maybe we should persist which panel the user selected, so it
    // remains selected across page loads.

    $content = array();
    $panel_list = array();
    $no_headers = PhabricatorDashboardPanelRenderingEngine::HEADER_MODE_NONE;
    foreach ($config as $idx => $tab_spec) {
      $panel_id = idx($tab_spec, 'panelID');
      $subpanel = idx($panels, $panel_id);

      if ($subpanel) {
        $panel_content = id(new PhabricatorDashboardPanelRenderingEngine())
          ->setViewer($viewer)
          ->setEnableAsyncRendering(true)
          ->setContextObject($context_object)
          ->setParentPanelPHIDs($parent_phids)
          ->setPanel($subpanel)
          ->setPanelPHID($subpanel->getPHID())
          ->setHeaderMode($no_headers)
          ->renderPanel();
      } else {
        $panel_content = pht('(Invalid Panel)');
      }

      $content_id = celerity_generate_unique_node_id();

      $content[] = phutil_tag(
        'div',
        array(
          'id' => $content_id,
          'style' => ($idx == $selected) ? null : 'display: none',
        ),
        $panel_content);

      $panel_list[] = array(
        'panelKey' => (string)$idx,
        'panelContentID' => $content_id,
      );
    }

    if (!$content) {
      if ($is_edit) {
        $message = pht(
          'This tab panel does not have any tabs yet. Use "Add Tab..." to '.
          'create or place a tab.');
      } else {
        $message = pht(
          'This tab panel does not have any tabs yet.');
      }

      $content = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->setErrors(
          array(
            $message,
          ));

      $content = id(new PHUIBoxView())
        ->addClass('mlt mlb')
        ->appendChild($content);
    }

    Javelin::initBehavior('dashboard-tab-panel');

    return javelin_tag(
      'div',
      array(
        'sigil' => 'dashboard-tab-panel-container',
        'meta' => array(
          'panels' => $panel_list,
        ),
      ),
      array(
        $list,
        $content,
      ));
  }

  public function getSubpanelPHIDs(PhabricatorDashboardPanel $panel) {
    $config = $this->getPanelConfiguration($panel);

    $panel_ids = array();
    foreach ($config as $tab_key => $tab_spec) {
      $panel_ids[] = $tab_spec['panelID'];
    }

    if ($panel_ids) {
      $panels = id(new PhabricatorDashboardPanelQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withIDs($panel_ids)
        ->execute();
    } else {
      $panels = array();
    }

    return mpull($panels, 'getPHID');
  }

}
