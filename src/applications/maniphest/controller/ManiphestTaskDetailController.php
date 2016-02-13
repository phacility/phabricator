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

    $e_commit = ManiphestTaskHasCommitEdgeType::EDGECONST;
    $e_dep_on = ManiphestTaskDependsOnTaskEdgeType::EDGECONST;
    $e_dep_by = ManiphestTaskDependedOnByTaskEdgeType::EDGECONST;
    $e_rev    = ManiphestTaskHasRevisionEdgeType::EDGECONST;
    $e_mock   = ManiphestTaskHasMockEdgeType::EDGECONST;

    $phid = $task->getPHID();

    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($phid))
      ->withEdgeTypes(
        array(
          $e_commit,
          $e_dep_on,
          $e_dep_by,
          $e_rev,
          $e_mock,
        ));
    $edges = idx($query->execute(), $phid);
    $phids = array_fill_keys($query->getDestinationPHIDs(), true);

    if ($task->getOwnerPHID()) {
      $phids[$task->getOwnerPHID()] = true;
    }
    $phids[$task->getAuthorPHID()] = true;

    $phids = array_keys($phids);
    $handles = $viewer->loadHandles($phids);

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer)
      ->setContextObject($task)
      ->addObject($task, ManiphestTask::MARKUP_FIELD_DESCRIPTION);

    $timeline = $this->buildTransactionTimeline(
      $task,
      new ManiphestTransactionQuery(),
      $engine);

    $actions = $this->buildActionView($task);

    $monogram = $task->getMonogram();
    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($monogram, '/'.$monogram);

    $header = $this->buildHeaderView($task);
    $properties = $this->buildPropertyView(
      $task, $field_list, $edges, $actions, $handles);
    $description = $this->buildDescriptionView($task, $engine);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    if ($description) {
      $object_box->addPropertyList($description);
    }

    $title = pht('%s %s', $monogram, $task->getTitle());

    $comment_view = id(new ManiphestEditEngine())
      ->setViewer($viewer)
      ->buildEditEngineCommentView($task);

    $timeline->setQuoteRef($monogram);
    $comment_view->setTransactionTimeline($timeline);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(
        array(
          $task->getPHID(),
        ))
      ->appendChild(
        array(
          $object_box,
          $timeline,
          $comment_view,
        ));
  }

  private function buildHeaderView(ManiphestTask $task) {
    $view = id(new PHUIHeaderView())
      ->setHeader($task->getTitle())
      ->setUser($this->getRequest()->getUser())
      ->setPolicyObject($task);

    $status = $task->getStatus();
    $status_name = ManiphestTaskStatus::renderFullDescription($status);

    $view->addProperty(PHUIHeaderView::PROPERTY_STATUS, $status_name);

    return $view;
  }


  private function buildActionView(ManiphestTask $task) {
    $viewer = $this->getRequest()->getUser();

    $id = $task->getID();
    $phid = $task->getPHID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $task,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($task);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Task'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("/task/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Merge Duplicates In'))
        ->setHref("/search/attach/{$phid}/TASK/merge/")
        ->setWorkflow(true)
        ->setIcon('fa-compress')
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    $edit_config = id(new ManiphestEditEngine())
      ->setViewer($viewer)
      ->loadDefaultEditConfiguration();

    $can_create = (bool)$edit_config;
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

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Create Subtask'))
        ->setHref($edit_uri)
        ->setIcon('fa-level-down')
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Blocking Tasks'))
        ->setHref("/search/attach/{$phid}/TASK/blocks/")
        ->setWorkflow(true)
        ->setIcon('fa-link')
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $view;
  }

  private function buildPropertyView(
    ManiphestTask $task,
    PhabricatorCustomFieldList $field_list,
    array $edges,
    PhabricatorActionListView $actions,
    $handles) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($task)
      ->setActionList($actions);

    $owner_phid = $task->getOwnerPHID();
    if ($owner_phid) {
      $assigned_to = $handles
        ->renderHandle($owner_phid)
        ->setShowHovercard(true);
    } else {
      $assigned_to = phutil_tag('em', array(), pht('None'));
    }

    $view->addProperty(pht('Assigned To'), $assigned_to);

    $view->addProperty(
      pht('Priority'),
      ManiphestTaskPriority::getTaskPriorityName($task->getPriority()));

    $author = $handles
      ->renderHandle($task->getAuthorPHID())
      ->setShowHovercard(true);

    $view->addProperty(pht('Author'), $author);

    if (ManiphestTaskPoints::getIsEnabled()) {
      $points = $task->getPoints();
      if ($points !== null) {
        $view->addProperty(
          ManiphestTaskPoints::getPointsLabel(),
          $task->getPoints());
      }
    }

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
      ManiphestTaskDependedOnByTaskEdgeType::EDGECONST
        => pht('Blocks'),
      ManiphestTaskDependsOnTaskEdgeType::EDGECONST
        => pht('Blocked By'),
      ManiphestTaskHasRevisionEdgeType::EDGECONST
        => pht('Differential Revisions'),
      ManiphestTaskHasMockEdgeType::EDGECONST
        => pht('Pholio Mocks'),
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
          ->setShowHovercard(true);
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
      if ($edges[$edge_type]) {
        $edge_handles = $viewer->loadHandles(array_keys($edges[$edge_type]));
        $view->addProperty(
          $edge_name,
          $edge_handles->renderList());
      }
    }

    if ($revisions_commits) {
      $view->addProperty(
        pht('Commits'),
        phutil_implode_html(phutil_tag('br'), $revisions_commits));
    }

    $view->invokeWillRenderEvent();

    $field_list->appendFieldsToPropertyList(
      $task,
      $viewer,
      $view);

    return $view;
  }

  private function buildDescriptionView(
    ManiphestTask $task,
    PhabricatorMarkupEngine $engine) {

    $section = null;
    if (strlen($task->getDescription())) {
      $section = new PHUIPropertyListView();
      $section->addSectionHeader(
        pht('Description'),
        PHUIPropertyListView::ICON_SUMMARY);
      $section->addTextContent(
        phutil_tag(
          'div',
          array(
            'class' => 'phabricator-remarkup',
          ),
          $engine->getOutput($task, ManiphestTask::MARKUP_FIELD_DESCRIPTION)));
    }

    return $section;
  }

}
