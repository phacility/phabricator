<?php

final class ManiphestTaskDetailController extends ManiphestController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $task = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needSubscriberPHIDs(true)
      ->executeOne();
    if (!$task) {
      return new Aphront404Response();
    }

    $field_list = PhabricatorCustomField::getObjectFields(
      $task,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($task);

    $edit_engine = id(new ManiphestEditEngine())
      ->setViewer($viewer)
      ->setTargetObject($task);

    $edge_types = array(
      ManiphestTaskHasCommitEdgeType::EDGECONST,
      ManiphestTaskHasRevisionEdgeType::EDGECONST,
      ManiphestTaskHasMockEdgeType::EDGECONST,
      PhabricatorObjectMentionedByObjectEdgeType::EDGECONST,
      PhabricatorObjectMentionsObjectEdgeType::EDGECONST,
      ManiphestTaskHasDuplicateTaskEdgeType::EDGECONST,
    );

    $phid = $task->getPHID();

    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($phid))
      ->withEdgeTypes($edge_types);
    $edges = idx($query->execute(), $phid);
    $phids = array_fill_keys($query->getDestinationPHIDs(), true);

    if ($task->getOwnerPHID()) {
      $phids[$task->getOwnerPHID()] = true;
    }
    $phids[$task->getAuthorPHID()] = true;

    $phids = array_keys($phids);
    $handles = $viewer->loadHandles($phids);

    $timeline = $this->buildTransactionTimeline(
      $task,
      new ManiphestTransactionQuery());

    $monogram = $task->getMonogram();
    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($monogram)
      ->setBorder(true);

    $header = $this->buildHeaderView($task);
    $details = $this->buildPropertyView($task, $field_list, $edges, $handles);
    $description = $this->buildDescriptionView($task);
    $curtain = $this->buildCurtain($task, $edit_engine);

    $title = pht('%s %s', $monogram, $task->getTitle());

    $comment_view = $edit_engine
      ->buildEditEngineCommentView($task);

    $timeline->setQuoteRef($monogram);
    $comment_view->setTransactionTimeline($timeline);

    $related_tabs = array();
    $graph_menu = null;

    $graph_limit = 100;
    $task_graph = id(new ManiphestTaskGraph())
      ->setViewer($viewer)
      ->setSeedPHID($task->getPHID())
      ->setLimit($graph_limit)
      ->loadGraph();
    if (!$task_graph->isEmpty()) {
      $parent_type = ManiphestTaskDependedOnByTaskEdgeType::EDGECONST;
      $subtask_type = ManiphestTaskDependsOnTaskEdgeType::EDGECONST;
      $parent_map = $task_graph->getEdges($parent_type);
      $subtask_map = $task_graph->getEdges($subtask_type);
      $parent_list = idx($parent_map, $task->getPHID(), array());
      $subtask_list = idx($subtask_map, $task->getPHID(), array());
      $has_parents = (bool)$parent_list;
      $has_subtasks = (bool)$subtask_list;

      $search_text = pht('Search...');

      // First, get a count of direct parent tasks and subtasks. If there
      // are too many of these, we just don't draw anything. You can use
      // the search button to browse tasks with the search UI instead.
      $direct_count = count($parent_list) + count($subtask_list);

      if ($direct_count > $graph_limit) {
        $message = pht(
          'Task graph too large to display (this task is directly connected '.
          'to more than %s other tasks). Use %s to explore connected tasks.',
          $graph_limit,
          phutil_tag('strong', array(), $search_text));
        $message = phutil_tag('em', array(), $message);
        $graph_table = id(new PHUIPropertyListView())
          ->addTextContent($message);
      } else {
        // If there aren't too many direct tasks, but there are too many total
        // tasks, we'll only render directly connected tasks.
        if ($task_graph->isOverLimit()) {
          $task_graph->setRenderOnlyAdjacentNodes(true);
        }
        $graph_table = $task_graph->newGraphTable();
      }

      $parents_uri = urisprintf(
        '/?subtaskIDs=%d#R',
        $task->getID());
      $parents_uri = $this->getApplicationURI($parents_uri);

      $subtasks_uri = urisprintf(
        '/?parentIDs=%d#R',
        $task->getID());
      $subtasks_uri = $this->getApplicationURI($subtasks_uri);

      $dropdown_menu = id(new PhabricatorActionListView())
        ->setViewer($viewer)
        ->addAction(
          id(new PhabricatorActionView())
            ->setHref($parents_uri)
            ->setName(pht('Search Parent Tasks'))
            ->setDisabled(!$has_parents)
            ->setIcon('fa-chevron-circle-up'))
        ->addAction(
          id(new PhabricatorActionView())
            ->setHref($subtasks_uri)
            ->setName(pht('Search Subtasks'))
            ->setDisabled(!$has_subtasks)
            ->setIcon('fa-chevron-circle-down'));

      $graph_menu = id(new PHUIButtonView())
        ->setTag('a')
        ->setIcon('fa-search')
        ->setText($search_text)
        ->setDropdownMenu($dropdown_menu);

      $related_tabs[] = id(new PHUITabView())
        ->setName(pht('Task Graph'))
        ->setKey('graph')
        ->appendChild($graph_table);
    }

    $related_tabs[] = $this->newMocksTab($task, $query);
    $related_tabs[] = $this->newMentionsTab($task, $query);
    $related_tabs[] = $this->newDuplicatesTab($task, $query);

    $tab_view = null;

    $related_tabs = array_filter($related_tabs);
    if ($related_tabs) {
      $tab_group = new PHUITabGroupView();
      foreach ($related_tabs as $tab) {
        $tab_group->addTab($tab);
      }

      $related_header = id(new PHUIHeaderView())
        ->setHeader(pht('Related Objects'));

      if ($graph_menu) {
        $related_header->addActionLink($graph_menu);
      }

      $tab_view = id(new PHUIObjectBoxView())
        ->setHeader($related_header)
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->addTabGroup($tab_group);
    }

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $tab_view,
          $timeline,
          $comment_view,
        ))
      ->addPropertySection(pht('Description'), $description)
      ->addPropertySection(pht('Details'), $details);


    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(
        array(
          $task->getPHID(),
        ))
      ->appendChild($view);

  }

  private function buildHeaderView(ManiphestTask $task) {
    $view = id(new PHUIHeaderView())
      ->setHeader($task->getTitle())
      ->setUser($this->getRequest()->getUser())
      ->setPolicyObject($task);

    $priority_name = ManiphestTaskPriority::getTaskPriorityName(
      $task->getPriority());
    $priority_color = ManiphestTaskPriority::getTaskPriorityColor(
      $task->getPriority());

    $status = $task->getStatus();
    $status_name = ManiphestTaskStatus::renderFullDescription(
      $status, $priority_name);
    $view->addProperty(PHUIHeaderView::PROPERTY_STATUS, $status_name);

    $view->setHeaderIcon(ManiphestTaskStatus::getStatusIcon(
      $task->getStatus()).' '.$priority_color);

    if (ManiphestTaskPoints::getIsEnabled()) {
      $points = $task->getPoints();
      if ($points !== null) {
        $points_name = pht('%s %s',
          $task->getPoints(),
          ManiphestTaskPoints::getPointsLabel());
        $tag = id(new PHUITagView())
          ->setName($points_name)
          ->setColor(PHUITagView::COLOR_BLUE)
          ->setType(PHUITagView::TYPE_SHADE);

        $view->addTag($tag);
      }
    }

    $subtype = $task->newSubtypeObject();
    if ($subtype && $subtype->hasTagView()) {
      $subtype_tag = $subtype->newTagView();
      $view->addTag($subtype_tag);
    }

    return $view;
  }


  private function buildCurtain(
    ManiphestTask $task,
    PhabricatorEditEngine $edit_engine) {
    $viewer = $this->getViewer();

    $id = $task->getID();
    $phid = $task->getPHID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $task,
      PhabricatorPolicyCapability::CAN_EDIT);

    $can_interact = PhabricatorPolicyFilter::canInteract($viewer, $task);

    // We expect a policy dialog if you can't edit the task, and expect a
    // lock override dialog if you can't interact with it.
    $workflow_edit = (!$can_edit || !$can_interact);

    $curtain = $this->newCurtainView($task);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Task'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("/task/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow($workflow_edit));

    $edit_config = $edit_engine->loadDefaultEditConfiguration($task);
    $can_create = (bool)$edit_config;

    $can_reassign = $edit_engine->hasEditAccessToTransaction(
      ManiphestTaskOwnerTransaction::TRANSACTIONTYPE);

    if ($can_create) {
      $form_key = $edit_config->getIdentifier();
      $edit_uri = id(new PhutilURI("/task/edit/form/{$form_key}/"))
        ->setQueryParam('parent', $id)
        ->setQueryParam('template', $id)
        ->setQueryParam('status', ManiphestTaskStatus::getDefaultStatus());
      $edit_uri = $this->getApplicationURI($edit_uri);
    } else {
      // TODO: This will usually give us a somewhat-reasonable error page, but
      // could be a bit cleaner.
      $edit_uri = "/task/edit/{$id}/";
      $edit_uri = $this->getApplicationURI($edit_uri);
    }

    $subtask_item = id(new PhabricatorActionView())
      ->setName(pht('Create Subtask'))
      ->setHref($edit_uri)
      ->setIcon('fa-level-down')
      ->setDisabled(!$can_create)
      ->setWorkflow(!$can_create);

    $relationship_list = PhabricatorObjectRelationshipList::newForObject(
      $viewer,
      $task);

    $submenu_actions = array(
      $subtask_item,
      ManiphestTaskHasParentRelationship::RELATIONSHIPKEY,
      ManiphestTaskHasSubtaskRelationship::RELATIONSHIPKEY,
      ManiphestTaskMergeInRelationship::RELATIONSHIPKEY,
      ManiphestTaskCloseAsDuplicateRelationship::RELATIONSHIPKEY,
    );

    $task_submenu = $relationship_list->newActionSubmenu($submenu_actions)
      ->setName(pht('Edit Related Tasks...'))
      ->setIcon('fa-anchor');

    $curtain->addAction($task_submenu);

    $relationship_submenu = $relationship_list->newActionMenu();
    if ($relationship_submenu) {
      $curtain->addAction($relationship_submenu);
    }

    $owner_phid = $task->getOwnerPHID();
    $author_phid = $task->getAuthorPHID();
    $handles = $viewer->loadHandles(array($owner_phid, $author_phid));

    if ($owner_phid) {
      $image_uri = $handles[$owner_phid]->getImageURI();
      $image_href = $handles[$owner_phid]->getURI();
      $owner = $viewer->renderHandle($owner_phid)->render();
      $content = phutil_tag('strong', array(), $owner);
      $assigned_to = id(new PHUIHeadThingView())
        ->setImage($image_uri)
        ->setImageHref($image_href)
        ->setContent($content);
    } else {
      $assigned_to = phutil_tag('em', array(), pht('None'));
    }

    $curtain->newPanel()
      ->setHeaderText(pht('Assigned To'))
      ->appendChild($assigned_to);

    $author_uri = $handles[$author_phid]->getImageURI();
    $author_href = $handles[$author_phid]->getURI();
    $author = $viewer->renderHandle($author_phid)->render();
    $content = phutil_tag('strong', array(), $author);
    $date = phabricator_date($task->getDateCreated(), $viewer);
    $content = pht('%s, %s', $content, $date);
    $authored_by = id(new PHUIHeadThingView())
      ->setImage($author_uri)
      ->setImageHref($author_href)
      ->setContent($content);

    $curtain->newPanel()
      ->setHeaderText(pht('Authored By'))
      ->appendChild($authored_by);

    return $curtain;
  }

  private function buildPropertyView(
    ManiphestTask $task,
    PhabricatorCustomFieldList $field_list,
    array $edges,
    $handles) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $source = $task->getOriginalEmailSource();
    if ($source) {
      $subject = '[T'.$task->getID().'] '.$task->getTitle();
      $view->addProperty(
        pht('From Email'),
        phutil_tag(
          'a',
          array(
            'href' => 'mailto:'.$source.'?subject='.$subject,
          ),
          $source));
    }

    $edge_types = array(
      ManiphestTaskHasRevisionEdgeType::EDGECONST
        => pht('Differential Revisions'),
    );

    $revisions_commits = array();

    $commit_phids = array_keys(
      $edges[ManiphestTaskHasCommitEdgeType::EDGECONST]);
    if ($commit_phids) {
      $commit_drev = DiffusionCommitHasRevisionEdgeType::EDGECONST;
      $drev_edges = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs($commit_phids)
        ->withEdgeTypes(array($commit_drev))
        ->execute();

      foreach ($commit_phids as $phid) {
        $revisions_commits[$phid] = $handles->renderHandle($phid)
          ->setShowHovercard(true)
          ->setShowStateIcon(true);
        $revision_phid = key($drev_edges[$phid][$commit_drev]);
        $revision_handle = $handles->getHandleIfExists($revision_phid);
        if ($revision_handle) {
          $task_drev = ManiphestTaskHasRevisionEdgeType::EDGECONST;
          unset($edges[$task_drev][$revision_phid]);
          $revisions_commits[$phid] = hsprintf(
            '%s / %s',
            $revision_handle->renderHovercardLink($revision_handle->getName()),
            $revisions_commits[$phid]);
        }
      }
    }

    foreach ($edge_types as $edge_type => $edge_name) {
      if (!$edges[$edge_type]) {
        continue;
      }

      $edge_handles = $viewer->loadHandles(array_keys($edges[$edge_type]));

      $edge_list = $edge_handles->renderList()
        ->setShowStateIcons(true);

      $view->addProperty($edge_name, $edge_list);
    }

    if ($revisions_commits) {
      $view->addProperty(
        pht('Commits'),
        phutil_implode_html(phutil_tag('br'), $revisions_commits));
    }

    $field_list->appendFieldsToPropertyList(
      $task,
      $viewer,
      $view);

    if ($view->hasAnyProperties()) {
      return $view;
    }

    return null;
  }

  private function buildDescriptionView(ManiphestTask $task) {
    $viewer = $this->getViewer();

    $section = null;

    $description = $task->getDescription();
    if (strlen($description)) {
      $section = new PHUIPropertyListView();
      $section->addTextContent(
        phutil_tag(
          'div',
          array(
            'class' => 'phabricator-remarkup',
          ),
          id(new PHUIRemarkupView($viewer, $description))
            ->setContextObject($task)));
    }

    return $section;
  }

  private function newMocksTab(
    ManiphestTask $task,
    PhabricatorEdgeQuery $edge_query) {

    $mock_type = ManiphestTaskHasMockEdgeType::EDGECONST;
    $mock_phids = $edge_query->getDestinationPHIDs(array(), array($mock_type));
    if (!$mock_phids) {
      return null;
    }

    $viewer = $this->getViewer();
    $handles = $viewer->loadHandles($mock_phids);

    // TODO: It would be nice to render this as pinboard-style thumbnails,
    // similar to "{M123}", instead of a list of links.

    $view = id(new PHUIPropertyListView())
      ->addProperty(pht('Mocks'), $handles->renderList());

    return id(new PHUITabView())
      ->setName(pht('Mocks'))
      ->setKey('mocks')
      ->appendChild($view);
  }

  private function newMentionsTab(
    ManiphestTask $task,
    PhabricatorEdgeQuery $edge_query) {

    $in_type = PhabricatorObjectMentionedByObjectEdgeType::EDGECONST;
    $out_type = PhabricatorObjectMentionsObjectEdgeType::EDGECONST;

    $in_phids = $edge_query->getDestinationPHIDs(array(), array($in_type));
    $out_phids = $edge_query->getDestinationPHIDs(array(), array($out_type));

    // Filter out any mentioned users from the list. These are not generally
    // very interesting to show in a relationship summary since they usually
    // end up as subscribers anyway.

    $user_type = PhabricatorPeopleUserPHIDType::TYPECONST;
    foreach ($out_phids as $key => $out_phid) {
      if (phid_get_type($out_phid) == $user_type) {
        unset($out_phids[$key]);
      }
    }

    if (!$in_phids && !$out_phids) {
      return null;
    }

    $viewer = $this->getViewer();
    $in_handles = $viewer->loadHandles($in_phids);
    $out_handles = $viewer->loadHandles($out_phids);

    $in_handles = $this->getCompleteHandles($in_handles);
    $out_handles = $this->getCompleteHandles($out_handles);

    if (!count($in_handles) && !count($out_handles)) {
      return null;
    }

    $view = new PHUIPropertyListView();

    if (count($in_handles)) {
      $view->addProperty(pht('Mentioned In'), $in_handles->renderList());
    }

    if (count($out_handles)) {
      $view->addProperty(pht('Mentioned Here'), $out_handles->renderList());
    }

    return id(new PHUITabView())
      ->setName(pht('Mentions'))
      ->setKey('mentions')
      ->appendChild($view);
  }

  private function newDuplicatesTab(
    ManiphestTask $task,
    PhabricatorEdgeQuery $edge_query) {

    $in_type = ManiphestTaskHasDuplicateTaskEdgeType::EDGECONST;
    $in_phids = $edge_query->getDestinationPHIDs(array(), array($in_type));

    $viewer = $this->getViewer();
    $in_handles = $viewer->loadHandles($in_phids);
    $in_handles = $this->getCompleteHandles($in_handles);

    $view = new PHUIPropertyListView();

    if (!count($in_handles)) {
      return null;
    }

    $view->addProperty(
      pht('Duplicates Merged Here'), $in_handles->renderList());

    return id(new PHUITabView())
      ->setName(pht('Duplicates'))
      ->setKey('duplicates')
      ->appendChild($view);
  }

  private function getCompleteHandles(PhabricatorHandleList $handles) {
    $phids = array();

    foreach ($handles as $phid => $handle) {
      if (!$handle->isComplete()) {
        continue;
      }
      $phids[] = $phid;
    }

    return $handles->newSublist($phids);
  }


}
