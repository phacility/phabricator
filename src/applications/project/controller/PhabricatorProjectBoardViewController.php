<?php

final class PhabricatorProjectBoardViewController
  extends PhabricatorProjectBoardController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $project = $this->getProject();
    $state = $this->getViewState();
    $board_uri = $project->getWorkboardURI();

    $search_engine = $state->getSearchEngine();
    $query_key = $state->getQueryKey();
    $saved = $state->getSavedQuery();
    if (!$saved) {
      return new Aphront404Response();
    }

    if ($saved->getID()) {
      $custom_query = $saved;
    } else {
      $custom_query = null;
    }

    $layout_engine = $state->getLayoutEngine();

    $board_phid = $project->getPHID();
    $columns = $layout_engine->getColumns($board_phid);
    if (!$columns || !$project->getHasWorkboard()) {
      $has_normal_columns = false;

      foreach ($columns as $column) {
        if (!$column->getProxyPHID()) {
          $has_normal_columns = true;
          break;
        }
      }

      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $project,
        PhabricatorPolicyCapability::CAN_EDIT);

      if (!$has_normal_columns) {
        if (!$can_edit) {
          $content = $this->buildNoAccessContent($project);
        } else {
          $content = $this->buildInitializeContent($project);
        }
      } else {
        if (!$can_edit) {
          $content = $this->buildDisabledContent($project);
        } else {
          $content = $this->buildEnableContent($project);
        }
      }

      if ($content instanceof AphrontResponse) {
        return $content;
      }

      $nav = $this->newNavigation(
        $project,
        PhabricatorProject::ITEM_WORKBOARD);

      $crumbs = $this->buildApplicationCrumbs();
      $crumbs->addTextCrumb(pht('Workboard'));

      return $this->newPage()
        ->setTitle(
          array(
            $project->getDisplayName(),
            pht('Workboard'),
          ))
        ->setNavigation($nav)
        ->setCrumbs($crumbs)
        ->appendChild($content);
    }

    $tasks = $state->getObjects();

    $task_can_edit_map = id(new PhabricatorPolicyFilter())
      ->setViewer($viewer)
      ->requireCapabilities(array(PhabricatorPolicyCapability::CAN_EDIT))
      ->apply($tasks);

    $board_id = celerity_generate_unique_node_id();

    $board = id(new PHUIWorkboardView())
      ->setUser($viewer)
      ->setID($board_id)
      ->addSigil('jx-workboard')
      ->setMetadata(
        array(
          'boardPHID' => $project->getPHID(),
        ));

    $visible_columns = array();
    $column_phids = array();
    $visible_phids = array();
    foreach ($columns as $column) {
      if (!$state->getShowHidden()) {
        if ($column->isHidden()) {
          continue;
        }
      }

      $proxy = $column->getProxy();
      if ($proxy && !$proxy->isMilestone()) {
        // TODO: For now, don't show subproject columns because we can't
        // handle tasks with multiple positions yet.
        continue;
      }

      $task_phids = $layout_engine->getColumnObjectPHIDs(
        $board_phid,
        $column->getPHID());

      $column_tasks = array_select_keys($tasks, $task_phids);
      $column_phid = $column->getPHID();

      $visible_columns[$column_phid] = $column;
      $column_phids[$column_phid] = $column_tasks;

      foreach ($column_tasks as $phid => $task) {
        $visible_phids[$phid] = $phid;
      }
    }

    $container_phids = $state->getBoardContainerPHIDs();

    $rendering_engine = id(new PhabricatorBoardRenderingEngine())
      ->setViewer($viewer)
      ->setObjects(array_select_keys($tasks, $visible_phids))
      ->setEditMap($task_can_edit_map)
      ->setExcludedProjectPHIDs($container_phids);

    $templates = array();
    $all_tasks = array();
    $column_templates = array();
    $sounds = array();
    foreach ($visible_columns as $column_phid => $column) {
      $column_tasks = $column_phids[$column_phid];

      $panel = id(new PHUIWorkpanelView())
        ->setHeader($column->getDisplayName())
        ->setSubHeader($column->getDisplayType())
        ->addSigil('workpanel');

      $proxy = $column->getProxy();
      if ($proxy) {
        $proxy_id = $proxy->getID();
        $href = $this->getApplicationURI("view/{$proxy_id}/");
        $panel->setHref($href);
      }

      $header_icon = $column->getHeaderIcon();
      if ($header_icon) {
        $panel->setHeaderIcon($header_icon);
      }

      $display_class = $column->getDisplayClass();
      if ($display_class) {
        $panel->addClass($display_class);
      }

      if ($column->isHidden()) {
        $panel->addClass('project-panel-hidden');
      }

      $column_menu = $this->buildColumnMenu($project, $column);
      $panel->addHeaderAction($column_menu);

      if ($column->canHaveTrigger()) {
        $trigger = $column->getTrigger();
        if ($trigger) {
          $trigger->setViewer($viewer);
        }

        $trigger_menu = $this->buildTriggerMenu($column);
        $panel->addHeaderAction($trigger_menu);
      }

      $count_tag = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_SHADE)
        ->setColor(PHUITagView::COLOR_BLUE)
        ->addSigil('column-points')
        ->setName(
          javelin_tag(
            'span',
            array(
              'sigil' => 'column-points-content',
            ),
            pht('-')))
        ->setStyle('display: none');

      $panel->setHeaderTag($count_tag);

      $cards = id(new PHUIObjectItemListView())
        ->setUser($viewer)
        ->setFlush(true)
        ->setAllowEmptyList(true)
        ->addSigil('project-column')
        ->setItemClass('phui-workcard')
        ->setMetadata(
          array(
            'columnPHID' => $column->getPHID(),
            'pointLimit' => $column->getPointLimit(),
          ));

      $card_phids = array();
      foreach ($column_tasks as $task) {
        $object_phid = $task->getPHID();

        $card = $rendering_engine->renderCard($object_phid);
        $templates[$object_phid] = hsprintf('%s', $card->getItem());
        $card_phids[] = $object_phid;

        $all_tasks[$object_phid] = $task;
      }

      $panel->setCards($cards);
      $board->addPanel($panel);

      $drop_effects = $column->getDropEffects();
      $drop_effects = mpull($drop_effects, 'toDictionary');

      $preview_effect = null;
      if ($column->canHaveTrigger()) {
        $trigger = $column->getTrigger();
        if ($trigger) {
          $preview_effect = $trigger->getPreviewEffect()
            ->toDictionary();

          foreach ($trigger->getSoundEffects() as $sound) {
            $sounds[] = $sound;
          }
        }
      }

      $column_templates[] = array(
        'columnPHID' => $column_phid,
        'effects' => $drop_effects,
        'cardPHIDs' => $card_phids,
        'triggerPreviewEffect' => $preview_effect,
      );
    }

    $order_key = $state->getOrder();

    $ordering_map = PhabricatorProjectColumnOrder::getEnabledOrders();
    $ordering = id(clone $ordering_map[$order_key])
      ->setViewer($viewer);

    $headers = $ordering->getHeadersForObjects($all_tasks);
    $headers = mpull($headers, 'toDictionary');

    $vectors = $ordering->getSortVectorsForObjects($all_tasks);
    $vector_map = array();
    foreach ($vectors as $task_phid => $vector) {
      $vector_map[$task_phid][$order_key] = $vector;
    }

    $header_keys = $ordering->getHeaderKeysForObjects($all_tasks);

    $order_maps = array();
    $order_maps[] = $ordering->toDictionary();

    $properties = array();
    foreach ($all_tasks as $task) {
      $properties[$task->getPHID()] =
        PhabricatorBoardResponseEngine::newTaskProperties($task);
    }

    $behavior_config = array(
      'moveURI' => $this->getApplicationURI('move/'.$project->getID().'/'),
      'uploadURI' => '/file/dropupload/',
      'coverURI' => $this->getApplicationURI('cover/'),
      'reloadURI' => phutil_string_cast($state->newWorkboardURI('reload/')),
      'chunkThreshold' => PhabricatorFileStorageEngine::getChunkThreshold(),
      'pointsEnabled' => ManiphestTaskPoints::getIsEnabled(),

      'boardPHID' => $project->getPHID(),
      'order' => $state->getOrder(),
      'orders' => $order_maps,
      'headers' => $headers,
      'headerKeys' => $header_keys,
      'templateMap' => $templates,
      'orderMaps' => $vector_map,
      'propertyMaps' => $properties,
      'columnTemplates' => $column_templates,

      'boardID' => $board_id,
      'projectPHID' => $project->getPHID(),
      'preloadSounds' => $sounds,
    );
    $this->initBehavior('project-boards', $behavior_config);

    $sort_menu = $this->buildSortMenu(
      $viewer,
      $project,
      $state->getOrder(),
      $ordering_map);

    $filter_menu = $this->buildFilterMenu(
      $viewer,
      $project,
      $custom_query,
      $search_engine,
      $query_key);

    $manage_menu = $this->buildManageMenu($project, $state->getShowHidden());

    $header_link = phutil_tag(
      'a',
      array(
        'href' => $this->getApplicationURI('profile/'.$project->getID().'/'),
      ),
      $project->getName());

    $board_box = id(new PHUIBoxView())
      ->appendChild($board)
      ->addClass('project-board-wrapper');

    $nav = $this->newNavigation(
      $project,
      PhabricatorProject::ITEM_WORKBOARD);

    $divider = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_DIVIDER);
    $fullscreen = $this->buildFullscreenMenu();

    $crumbs = $this->newWorkboardCrumbs();
    $crumbs->addTextCrumb(pht('Workboard'));
    $crumbs->setBorder(true);

    $crumbs->addAction($sort_menu);
    $crumbs->addAction($filter_menu);
    $crumbs->addAction($divider);
    $crumbs->addAction($manage_menu);
    $crumbs->addAction($fullscreen);

    $page = $this->newPage()
      ->setTitle(
        array(
          $project->getDisplayName(),
          pht('Workboard'),
        ))
      ->setPageObjectPHIDs(array($project->getPHID()))
      ->setShowFooter(false)
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->addQuicksandConfig(
        array(
          'boardConfig' => $behavior_config,
        ))
      ->appendChild(
        array(
          $board_box,
        ));

    $background = $project->getDisplayWorkboardBackgroundColor();
    require_celerity_resource('phui-workboard-color-css');
    if ($background !== null) {
      $background_color_class = "phui-workboard-{$background}";

      $page->addClass('phui-workboard-color');
      $page->addClass($background_color_class);
    } else {
      $page->addClass('phui-workboard-no-color');
    }

    return $page;
  }

  private function buildSortMenu(
    PhabricatorUser $viewer,
    PhabricatorProject $project,
    $sort_key,
    array $ordering_map) {

    $state = $this->getViewState();
    $base_uri = $state->newWorkboardURI();

    $items = array();
    foreach ($ordering_map as $key => $ordering) {
      // TODO: It would be desirable to build a real "PHUIIconView" here, but
      // the pathway for threading that through all the view classes ends up
      // being fairly complex, since some callers read the icon out of other
      // views. For now, just stick with a string.
      $ordering_icon = $ordering->getMenuIconIcon();
      $ordering_name = $ordering->getDisplayName();

      $is_selected = ($key === $sort_key);
      if ($is_selected) {
        $active_name = $ordering_name;
        $active_icon = $ordering_icon;
      }

      $item = id(new PhabricatorActionView())
        ->setIcon($ordering_icon)
        ->setSelected($is_selected)
        ->setName($ordering_name);

      $uri = $base_uri->alter('order', $key);
      $item->setHref($uri);

      $items[] = $item;
    }

    $id = $project->getID();

    $save_uri = $state->newWorkboardURI('default/sort/');

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $items[] = id(new PhabricatorActionView())
      ->setType(PhabricatorActionView::TYPE_DIVIDER);

    $items[] = id(new PhabricatorActionView())
      ->setIcon('fa-floppy-o')
      ->setName(pht('Save as Default'))
      ->setHref($save_uri)
      ->setWorkflow(true)
      ->setDisabled(!$can_edit);

    $sort_menu = id(new PhabricatorActionListView())
      ->setUser($viewer);
    foreach ($items as $item) {
      $sort_menu->addAction($item);
    }

    $sort_button = id(new PHUIListItemView())
      ->setName($active_name)
      ->setIcon($active_icon)
      ->setHref('#')
      ->addSigil('boards-dropdown-menu')
      ->setMetadata(
        array(
          'items' => hsprintf('%s', $sort_menu),
        ));

    return $sort_button;
  }

  private function buildFilterMenu(
    PhabricatorUser $viewer,
    PhabricatorProject $project,
    $custom_query,
    PhabricatorApplicationSearchEngine $engine,
    $query_key) {

    $state = $this->getViewState();

    $named = array(
      'open' => pht('Open Tasks'),
      'all' => pht('All Tasks'),
    );

    if ($viewer->isLoggedIn()) {
      $named['assigned'] = pht('Assigned to Me');
    }

    if ($custom_query) {
      $named[$custom_query->getQueryKey()] = pht('Custom Filter');
    }

    $items = array();
    foreach ($named as $key => $name) {
      $is_selected = ($key == $query_key);
      if ($is_selected) {
        $active_filter = $name;
      }

      $is_custom = false;
      if ($custom_query) {
        $is_custom = ($key == $custom_query->getQueryKey());
      }

      $item = id(new PhabricatorActionView())
        ->setIcon('fa-search')
        ->setSelected($is_selected)
        ->setName($name);

      if ($is_custom) {
        // When you're using a custom filter already and you select "Custom
        // Filter", you get a dialog back to let you edit the filter. This is
        // equivalent to selecting "Advanced Filter..." to configure a new
        // filter.
        $filter_uri = $state->newWorkboardURI('filter/');
        $item->setWorkflow(true);
      } else {
        $filter_uri = urisprintf('query/%s/', $key);
        $filter_uri = $state->newWorkboardURI($filter_uri);
        $filter_uri->removeQueryParam('filter');
      }

      $item->setHref($filter_uri);

      $items[] = $item;
    }

    $id = $project->getID();

    $filter_uri = $state->newWorkboardURI('filter/');

    $items[] = id(new PhabricatorActionView())
      ->setIcon('fa-cog')
      ->setHref($filter_uri)
      ->setWorkflow(true)
      ->setName(pht('Advanced Filter...'));

    $save_uri = $state->newWorkboardURI('default/filter/');

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $items[] = id(new PhabricatorActionView())
      ->setType(PhabricatorActionView::TYPE_DIVIDER);

    $items[] = id(new PhabricatorActionView())
      ->setIcon('fa-floppy-o')
      ->setName(pht('Save as Default'))
      ->setHref($save_uri)
      ->setWorkflow(true)
      ->setDisabled(!$can_edit);

    $filter_menu = id(new PhabricatorActionListView())
        ->setUser($viewer);
    foreach ($items as $item) {
      $filter_menu->addAction($item);
    }

    $filter_button = id(new PHUIListItemView())
      ->setName($active_filter)
      ->setIcon('fa-search')
      ->setHref('#')
      ->addSigil('boards-dropdown-menu')
      ->setMetadata(
        array(
          'items' => hsprintf('%s', $filter_menu),
        ));

    return $filter_button;
  }

  private function buildManageMenu(
    PhabricatorProject $project,
    $show_hidden) {

    $request = $this->getRequest();
    $viewer = $request->getUser();
    $state = $this->getViewState();

    $id = $project->getID();

    $manage_uri = $this->getApplicationURI("board/{$id}/manage/");
    $add_uri = $this->getApplicationURI("board/{$id}/edit/");

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $manage_items = array();

    $manage_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-plus')
      ->setName(pht('Add Column'))
      ->setHref($add_uri)
      ->setDisabled(!$can_edit)
      ->setWorkflow(true);

    $reorder_uri = $this->getApplicationURI("board/{$id}/reorder/");
    $manage_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-exchange')
      ->setName(pht('Reorder Columns'))
      ->setHref($reorder_uri)
      ->setDisabled(!$can_edit)
      ->setWorkflow(true);

    if ($show_hidden) {
      $hidden_uri = $state->newWorkboardURI()
        ->removeQueryParam('hidden');
      $hidden_icon = 'fa-eye-slash';
      $hidden_text = pht('Hide Hidden Columns');
    } else {
      $hidden_uri = $state->newWorkboardURI()
        ->replaceQueryParam('hidden', 'true');
      $hidden_icon = 'fa-eye';
      $hidden_text = pht('Show Hidden Columns');
    }

    $manage_items[] = id(new PhabricatorActionView())
      ->setIcon($hidden_icon)
      ->setName($hidden_text)
      ->setHref($hidden_uri);

    $manage_items[] = id(new PhabricatorActionView())
      ->setType(PhabricatorActionView::TYPE_DIVIDER);

    $background_uri = $this->getApplicationURI("board/{$id}/background/");
    $manage_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-paint-brush')
      ->setName(pht('Change Background Color'))
      ->setHref($background_uri)
      ->setDisabled(!$can_edit)
      ->setWorkflow(false);

    $manage_uri = $this->getApplicationURI("board/{$id}/manage/");
    $manage_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-gear')
      ->setName(pht('Manage Workboard'))
      ->setHref($manage_uri);

    $manage_menu = id(new PhabricatorActionListView())
        ->setUser($viewer);
    foreach ($manage_items as $item) {
      $manage_menu->addAction($item);
    }

    $manage_button = id(new PHUIListItemView())
      ->setIcon('fa-cog')
      ->setHref('#')
      ->addSigil('boards-dropdown-menu')
      ->addSigil('has-tooltip')
      ->setMetadata(
        array(
          'tip' => pht('Manage'),
          'align' => 'S',
          'items' => hsprintf('%s', $manage_menu),
        ));

    return $manage_button;
  }

  private function buildFullscreenMenu() {

    $up = id(new PHUIListItemView())
      ->setIcon('fa-arrows-alt')
      ->setHref('#')
      ->addClass('phui-workboard-expand-icon')
      ->addSigil('jx-toggle-class')
      ->addSigil('has-tooltip')
      ->setMetaData(array(
        'tip' => pht('Fullscreen'),
        'map' => array(
          'phabricator-standard-page' => 'phui-workboard-fullscreen',
        ),
      ));

    return $up;
  }

  private function buildColumnMenu(
    PhabricatorProject $project,
    PhabricatorProjectColumn $column) {

    $request = $this->getRequest();
    $viewer = $request->getUser();
    $state = $this->getViewState();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $column_items = array();

    if ($column->getProxyPHID()) {
      $default_phid = $column->getProxyPHID();
    } else {
      $default_phid = $column->getProjectPHID();
    }

    $specs = id(new ManiphestEditEngine())
      ->setViewer($viewer)
      ->newCreateActionSpecifications(array());

    foreach ($specs as $spec) {
      $column_items[] = id(new PhabricatorActionView())
        ->setIcon($spec['icon'])
        ->setName($spec['name'])
        ->setHref($spec['uri'])
        ->setDisabled($spec['disabled'])
        ->addSigil('column-add-task')
        ->setMetadata(
          array(
            'createURI' => $spec['uri'],
            'columnPHID' => $column->getPHID(),
            'boardPHID' => $project->getPHID(),
            'projectPHID' => $default_phid,
          ));
    }

    $column_items[] = id(new PhabricatorActionView())
      ->setType(PhabricatorActionView::TYPE_DIVIDER);

    $query_uri = urisprintf('viewquery/%d/', $column->getID());
    $query_uri = $state->newWorkboardURI($query_uri);

    $column_items[] = id(new PhabricatorActionView())
      ->setName(pht('View Tasks as Query'))
      ->setIcon('fa-search')
      ->setHref($query_uri);

    $column_move_uri = urisprintf('bulkmove/%d/column/', $column->getID());
    $column_move_uri = $state->newWorkboardURI($column_move_uri);

    $column_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-arrows-h')
      ->setName(pht('Move Tasks to Column...'))
      ->setHref($column_move_uri)
      ->setWorkflow(true);

    $project_move_uri = urisprintf('bulkmove/%d/project/', $column->getID());
    $project_move_uri = $state->newWorkboardURI($project_move_uri);

    $column_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-arrows')
      ->setName(pht('Move Tasks to Project...'))
      ->setHref($project_move_uri)
      ->setWorkflow(true);

    $bulk_edit_uri = urisprintf('bulk/%d/', $column->getID());
    $bulk_edit_uri = $state->newWorkboardURI($bulk_edit_uri);

    $can_bulk_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      PhabricatorApplication::getByClass('PhabricatorManiphestApplication'),
      ManiphestBulkEditCapability::CAPABILITY);

    $column_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-pencil-square-o')
      ->setName(pht('Bulk Edit Tasks...'))
      ->setHref($bulk_edit_uri)
      ->setDisabled(!$can_bulk_edit);

    $column_items[] = id(new PhabricatorActionView())
      ->setType(PhabricatorActionView::TYPE_DIVIDER);


    $edit_uri = 'board/'.$project->getID().'/edit/'.$column->getID().'/';
    $column_items[] = id(new PhabricatorActionView())
      ->setName(pht('Edit Column'))
      ->setIcon('fa-pencil')
      ->setHref($this->getApplicationURI($edit_uri))
      ->setDisabled(!$can_edit)
      ->setWorkflow(true);

    $can_hide = ($can_edit && !$column->isDefaultColumn());

    $hide_uri = urisprintf('hide/%d/', $column->getID());
    $hide_uri = $state->newWorkboardURI($hide_uri);

    if (!$column->isHidden()) {
      $column_items[] = id(new PhabricatorActionView())
        ->setName(pht('Hide Column'))
        ->setIcon('fa-eye-slash')
        ->setHref($hide_uri)
        ->setDisabled(!$can_hide)
        ->setWorkflow(true);
    } else {
      $column_items[] = id(new PhabricatorActionView())
        ->setName(pht('Show Column'))
        ->setIcon('fa-eye')
        ->setHref($hide_uri)
        ->setDisabled(!$can_hide)
        ->setWorkflow(true);
    }

    $column_menu = id(new PhabricatorActionListView())
      ->setUser($viewer);
    foreach ($column_items as $item) {
      $column_menu->addAction($item);
    }

    $column_button = id(new PHUIIconView())
      ->setIcon('fa-pencil')
      ->setHref('#')
      ->addSigil('boards-dropdown-menu')
      ->setMetadata(
        array(
          'items' => hsprintf('%s', $column_menu),
        ));

    return $column_button;
  }

  private function buildTriggerMenu(PhabricatorProjectColumn $column) {
    $viewer = $this->getViewer();
    $trigger = $column->getTrigger();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $column,
      PhabricatorPolicyCapability::CAN_EDIT);

    $trigger_items = array();
    if (!$trigger) {
      $set_uri = $this->getApplicationURI(
        new PhutilURI(
          'trigger/edit/',
          array(
            'columnPHID' => $column->getPHID(),
          )));

      $trigger_items[] = id(new PhabricatorActionView())
        ->setIcon('fa-cogs')
        ->setName(pht('New Trigger...'))
        ->setHref($set_uri)
        ->setDisabled(!$can_edit);
    } else {
      $trigger_items[] = id(new PhabricatorActionView())
        ->setIcon('fa-cogs')
        ->setName(pht('View Trigger'))
        ->setHref($trigger->getURI())
        ->setDisabled(!$can_edit);
    }

    $remove_uri = $this->getApplicationURI(
      new PhutilURI(
        urisprintf(
          'column/remove/%d/',
          $column->getID())));

    $trigger_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-times')
      ->setName(pht('Remove Trigger'))
      ->setHref($remove_uri)
      ->setWorkflow(true)
      ->setDisabled(!$can_edit || !$trigger);

    $trigger_menu = id(new PhabricatorActionListView())
      ->setUser($viewer);
    foreach ($trigger_items as $item) {
      $trigger_menu->addAction($item);
    }

    if ($trigger) {
      $trigger_icon = 'fa-cogs';
    } else {
      $trigger_icon = 'fa-cogs grey';
    }

    $trigger_button = id(new PHUIIconView())
      ->setIcon($trigger_icon)
      ->setHref('#')
      ->addSigil('boards-dropdown-menu')
      ->addSigil('trigger-preview')
      ->setMetadata(
        array(
          'items' => hsprintf('%s', $trigger_menu),
          'columnPHID' => $column->getPHID(),
        ));

    return $trigger_button;
  }

  private function buildInitializeContent(PhabricatorProject $project) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $type = $request->getStr('initialize-type');

    $id = $project->getID();

    $profile_uri = $this->getApplicationURI("profile/{$id}/");
    $board_uri = $this->getApplicationURI("board/{$id}/");
    $import_uri = $this->getApplicationURI("board/{$id}/import/");

    $set_default = $request->getBool('default');
    if ($set_default) {
      $this
        ->getProfileMenuEngine()
        ->adjustDefault(PhabricatorProject::ITEM_WORKBOARD);
    }

    if ($request->isFormPost()) {
      if ($type == 'backlog-only') {
        $column = PhabricatorProjectColumn::initializeNewColumn($viewer)
          ->setSequence(0)
          ->setProperty('isDefault', true)
          ->setProjectPHID($project->getPHID())
          ->save();

          $xactions = array();
          $xactions[] = id(new PhabricatorProjectTransaction())
            ->setTransactionType(
                PhabricatorProjectWorkboardTransaction::TRANSACTIONTYPE)
            ->setNewValue(1);

          id(new PhabricatorProjectTransactionEditor())
            ->setActor($viewer)
            ->setContentSourceFromRequest($request)
            ->setContinueOnNoEffect(true)
            ->setContinueOnMissingFields(true)
            ->applyTransactions($project, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI($board_uri);
      } else {
        return id(new AphrontRedirectResponse())
          ->setURI($import_uri);
      }
    }

    // TODO: Tailor this UI if the project is already a parent project. We
    // should not offer options for creating a parent project workboard, since
    // they can't have their own columns.

    $new_selector = id(new AphrontFormRadioButtonControl())
      ->setLabel(pht('Columns'))
      ->setName('initialize-type')
      ->setValue('backlog-only')
      ->addButton(
        'backlog-only',
        pht('New Empty Board'),
        pht('Create a new board with just a backlog column.'))
      ->addButton(
        'import',
        pht('Import Columns'),
        pht('Import board columns from another project.'));

    $default_checkbox = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Make Default'))
      ->addCheckbox(
        'default',
        1,
        pht('Make the workboard the default view for this project.'),
        true);

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('initialize', 1)
      ->appendRemarkupInstructions(
        pht('The workboard for this project has not been created yet.'))
      ->appendControl($new_selector)
      ->appendControl($default_checkbox)
      ->appendControl(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($profile_uri)
          ->setValue(pht('Create Workboard')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Create Workboard'))
      ->setForm($form);

    return $box;
  }

  private function buildNoAccessContent(PhabricatorProject $project) {
    $viewer = $this->getViewer();

    $id = $project->getID();

    $profile_uri = $this->getApplicationURI("profile/{$id}/");

    return $this->newDialog()
      ->setTitle(pht('Unable to Create Workboard'))
      ->appendParagraph(
        pht(
          'The workboard for this project has not been created yet, '.
          'but you do not have permission to create it. Only users '.
          'who can edit this project can create a workboard for it.'))
      ->addCancelButton($profile_uri);
  }


  private function buildEnableContent(PhabricatorProject $project) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $id = $project->getID();
    $profile_uri = $this->getApplicationURI("profile/{$id}/");
    $board_uri = $this->getApplicationURI("board/{$id}/");

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(
            PhabricatorProjectWorkboardTransaction::TRANSACTIONTYPE)
        ->setNewValue(1);

      id(new PhabricatorProjectTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($project, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($board_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Workboard Disabled'))
      ->addHiddenInput('initialize', 1)
      ->appendParagraph(
        pht(
          'This workboard has been disabled, but can be restored to its '.
          'former glory.'))
      ->addCancelButton($profile_uri)
      ->addSubmitButton(pht('Enable Workboard'));
  }

  private function buildDisabledContent(PhabricatorProject $project) {
    $viewer = $this->getViewer();

    $id = $project->getID();

    $profile_uri = $this->getApplicationURI("profile/{$id}/");

    return $this->newDialog()
      ->setTitle(pht('Workboard Disabled'))
      ->appendParagraph(
        pht(
          'This workboard has been disabled, and you do not have permission '.
          'to enable it. Only users who can edit this project can restore '.
          'the workboard.'))
      ->addCancelButton($profile_uri);
  }

}
