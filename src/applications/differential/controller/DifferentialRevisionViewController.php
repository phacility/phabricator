<?php

final class DifferentialRevisionViewController extends DifferentialController {

  private $revisionID;

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $this->revisionID = $request->getURIData('id');

    $viewer_is_anonymous = !$viewer->isLoggedIn();

    $revision = id(new DifferentialRevisionQuery())
      ->withIDs(array($this->revisionID))
      ->setViewer($request->getUser())
      ->needRelationships(true)
      ->needReviewerStatus(true)
      ->needReviewerAuthority(true)
      ->executeOne();

    if (!$revision) {
      return new Aphront404Response();
    }

    $diffs = id(new DifferentialDiffQuery())
      ->setViewer($request->getUser())
      ->withRevisionIDs(array($this->revisionID))
      ->execute();
    $diffs = array_reverse($diffs, $preserve_keys = true);

    if (!$diffs) {
      throw new Exception(
        pht('This revision has no diffs. Something has gone quite wrong.'));
    }

    $revision->attachActiveDiff(last($diffs));

    $diff_vs = $request->getInt('vs');
    $target_id = $request->getInt('id');
    $target = idx($diffs, $target_id, end($diffs));

    $target_manual = $target;
    if (!$target_id) {
      foreach ($diffs as $diff) {
        if ($diff->getCreationMethod() != 'commit') {
          $target_manual = $diff;
        }
      }
    }

    if (empty($diffs[$diff_vs])) {
      $diff_vs = null;
    }

    $repository = null;
    $repository_phid = $target->getRepositoryPHID();
    if ($repository_phid) {
      if ($repository_phid == $revision->getRepositoryPHID()) {
        $repository = $revision->getRepository();
      } else {
        $repository = id(new PhabricatorRepositoryQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($repository_phid))
          ->executeOne();
      }
    }

    list($changesets, $vs_map, $vs_changesets, $rendering_references) =
      $this->loadChangesetsAndVsMap(
        $target,
        idx($diffs, $diff_vs),
        $repository);

    if ($request->getExists('download')) {
      return $this->buildRawDiffResponse(
        $revision,
        $changesets,
        $vs_changesets,
        $vs_map,
        $repository);
    }

    $map = $vs_map;
    if (!$map) {
      $map = array_fill_keys(array_keys($changesets), 0);
    }

    $old_ids = array();
    $new_ids = array();
    foreach ($map as $id => $vs) {
      if ($vs <= 0) {
        $old_ids[] = $id;
        $new_ids[] = $id;
      } else {
        $new_ids[] = $id;
        $new_ids[] = $vs;
      }
    }

    $this->loadDiffProperties($diffs);
    $props = $target_manual->getDiffProperties();

    $object_phids = array_merge(
      $revision->getReviewers(),
      $revision->getCCPHIDs(),
      $revision->loadCommitPHIDs(),
      array(
        $revision->getAuthorPHID(),
        $viewer->getPHID(),
      ));

    foreach ($revision->getAttached() as $type => $phids) {
      foreach ($phids as $phid => $info) {
        $object_phids[] = $phid;
      }
    }

    $field_list = PhabricatorCustomField::getObjectFields(
      $revision,
      PhabricatorCustomField::ROLE_VIEW);

    $field_list->setViewer($viewer);
    $field_list->readFieldsFromStorage($revision);

    $warning_handle_map = array();
    foreach ($field_list->getFields() as $key => $field) {
      $req = $field->getRequiredHandlePHIDsForRevisionHeaderWarnings();
      foreach ($req as $phid) {
        $warning_handle_map[$key][] = $phid;
        $object_phids[] = $phid;
      }
    }

    $handles = $this->loadViewerHandles($object_phids);

    $request_uri = $request->getRequestURI();

    $limit = 100;
    $large = $request->getStr('large');
    if (count($changesets) > $limit && !$large) {
      $count = count($changesets);
      $warning = new PHUIInfoView();
      $warning->setTitle(pht('Very Large Diff'));
      $warning->setSeverity(PHUIInfoView::SEVERITY_WARNING);
      $warning->appendChild(hsprintf(
        '%s <strong>%s</strong>',
        pht(
          'This diff is very large and affects %s files. '.
          'You may load each file individually or ',
          new PhutilNumber($count)),
        phutil_tag(
          'a',
          array(
            'class' => 'button grey',
            'href' => $request_uri
              ->alter('large', 'true')
              ->setFragment('toc'),
          ),
          pht('Show All Files Inline'))));
      $warning = $warning->render();

      $old = array_select_keys($changesets, $old_ids);
      $new = array_select_keys($changesets, $new_ids);

      $query = id(new DifferentialInlineCommentQuery())
        ->setViewer($viewer)
        ->needHidden(true)
        ->withRevisionPHIDs(array($revision->getPHID()));
      $inlines = $query->execute();
      $inlines = $query->adjustInlinesForChangesets(
        $inlines,
        $old,
        $new,
        $revision);

      $visible_changesets = array();
      foreach ($inlines as $inline) {
        $changeset_id = $inline->getChangesetID();
        if (isset($changesets[$changeset_id])) {
          $visible_changesets[$changeset_id] = $changesets[$changeset_id];
        }
      }
    } else {
      $warning = null;
      $visible_changesets = $changesets;
    }

    $commit_hashes = mpull($diffs, 'getSourceControlBaseRevision');
    $local_commits = idx($props, 'local:commits', array());
    foreach ($local_commits as $local_commit) {
      $commit_hashes[] = idx($local_commit, 'tree');
      $commit_hashes[] = idx($local_commit, 'local');
    }
    $commit_hashes = array_unique(array_filter($commit_hashes));
    if ($commit_hashes) {
      $commits_for_links = id(new DiffusionCommitQuery())
        ->setViewer($viewer)
        ->withIdentifiers($commit_hashes)
        ->execute();
      $commits_for_links = mpull(
        $commits_for_links,
        null,
        'getCommitIdentifier');
    } else {
      $commits_for_links = array();
    }

    $header = $this->buildHeader($revision);
    $subheader = $this->buildSubheaderView($revision);
    $details = $this->buildDetails($revision, $field_list);
    $curtain = $this->buildCurtain($revision);

    $whitespace = $request->getStr(
      'whitespace',
      DifferentialChangesetParser::WHITESPACE_IGNORE_MOST);

    $repository = $revision->getRepository();
    if ($repository) {
      $symbol_indexes = $this->buildSymbolIndexes(
        $repository,
        $visible_changesets);
    } else {
      $symbol_indexes = array();
    }

    $revision_warnings = $this->buildRevisionWarnings(
      $revision,
      $field_list,
      $warning_handle_map,
      $handles);
    $info_view = null;
    if ($revision_warnings) {
      $info_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors($revision_warnings);
    }

    $detail_diffs = array_select_keys(
      $diffs,
      array($diff_vs, $target->getID()));
    $detail_diffs = mpull($detail_diffs, null, 'getPHID');

    $this->loadHarbormasterData($detail_diffs);

    $diff_detail_box = $this->buildDiffDetailView(
      $detail_diffs,
      $revision,
      $field_list);

    $unit_box = $this->buildUnitMessagesView(
      $target,
      $revision);

    $comment_view = $this->buildTransactions(
      $revision,
      $diff_vs ? $diffs[$diff_vs] : $target,
      $target,
      $old_ids,
      $new_ids);

    if (!$viewer_is_anonymous) {
      $comment_view->setQuoteRef('D'.$revision->getID());
      $comment_view->setQuoteTargetID('comment-content');
    }

    $changeset_view = id(new DifferentialChangesetListView())
      ->setChangesets($changesets)
      ->setVisibleChangesets($visible_changesets)
      ->setStandaloneURI('/differential/changeset/')
      ->setRawFileURIs(
        '/differential/changeset/?view=old',
        '/differential/changeset/?view=new')
      ->setUser($viewer)
      ->setDiff($target)
      ->setRenderingReferences($rendering_references)
      ->setVsMap($vs_map)
      ->setWhitespace($whitespace)
      ->setSymbolIndexes($symbol_indexes)
      ->setTitle(pht('Diff %s', $target->getID()))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);

    if ($repository) {
      $changeset_view->setRepository($repository);
    }

    if (!$viewer_is_anonymous) {
      $changeset_view->setInlineCommentControllerURI(
        '/differential/comment/inline/edit/'.$revision->getID().'/');
    }

    $broken_diffs = $this->loadHistoryDiffStatus($diffs);

    $history = id(new DifferentialRevisionUpdateHistoryView())
      ->setUser($viewer)
      ->setDiffs($diffs)
      ->setDiffUnitStatuses($broken_diffs)
      ->setSelectedVersusDiffID($diff_vs)
      ->setSelectedDiffID($target->getID())
      ->setSelectedWhitespace($whitespace)
      ->setCommitsForLinks($commits_for_links);

    $local_table = id(new DifferentialLocalCommitsView())
      ->setUser($viewer)
      ->setLocalCommits(idx($props, 'local:commits'))
      ->setCommitsForLinks($commits_for_links);

    if ($repository) {
      $other_revisions = $this->loadOtherRevisions(
        $changesets,
        $target,
        $repository);
    } else {
      $other_revisions = array();
    }

    $other_view = null;
    if ($other_revisions) {
      $other_view = $this->renderOtherRevisions($other_revisions);
    }

    $toc_view = $this->buildTableOfContents(
      $changesets,
      $visible_changesets,
      $target->loadCoverageMap($viewer));

    $tab_group = id(new PHUITabGroupView())
      ->addTab(
        id(new PHUITabView())
          ->setName(pht('Files'))
          ->setKey('files')
          ->appendChild($toc_view))
      ->addTab(
        id(new PHUITabView())
          ->setName(pht('History'))
          ->setKey('history')
          ->appendChild($history))
      ->addTab(
        id(new PHUITabView())
          ->setName(pht('Commits'))
          ->setKey('commits')
          ->appendChild($local_table));

    $stack_graph = id(new DifferentialRevisionGraph())
      ->setViewer($viewer)
      ->setSeedPHID($revision->getPHID())
      ->setLoadEntireGraph(true)
      ->loadGraph();
    if (!$stack_graph->isEmpty()) {
      $stack_table = $stack_graph->newGraphTable();

      $parent_type = DifferentialRevisionDependsOnRevisionEdgeType::EDGECONST;
      $reachable = $stack_graph->getReachableObjects($parent_type);

      foreach ($reachable as $key => $reachable_revision) {
        if ($reachable_revision->isClosed()) {
          unset($reachable[$key]);
        }
      }

      if ($reachable) {
        $stack_name = pht('Stack (%s Open)', phutil_count($reachable));
        $stack_color = PHUIListItemView::STATUS_FAIL;
      } else {
        $stack_name = pht('Stack');
        $stack_color = null;
      }

      $tab_group->addTab(
        id(new PHUITabView())
          ->setName($stack_name)
          ->setKey('stack')
          ->setColor($stack_color)
          ->appendChild($stack_table));
    }

    if ($other_view) {
      $tab_group->addTab(
        id(new PHUITabView())
          ->setName(pht('Similar'))
          ->setKey('similar')
          ->appendChild($other_view));
    }

    $tab_view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Revision Contents'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addTabGroup($tab_group);

    $comment_form = null;
    if (!$viewer_is_anonymous) {
      $comment_form = $this->buildCommentForm($revision, $field_list);
    }

    $signatures = DifferentialRequiredSignaturesField::loadForRevision(
      $revision);
    $missing_signatures = false;
    foreach ($signatures as $phid => $signed) {
      if (!$signed) {
        $missing_signatures = true;
      }
    }

    $footer = array();
    $signature_message = null;
    if ($missing_signatures) {
      $signature_message = id(new PHUIInfoView())
        ->setTitle(pht('Content Hidden'))
        ->appendChild(
          pht(
            'The content of this revision is hidden until the author has '.
            'signed all of the required legal agreements.'));
    } else {
      $anchor = id(new PhabricatorAnchorView())
        ->setAnchorName('toc')
        ->setNavigationMarker(true);

      $footer[] = array(
        $anchor,
        $warning,
        $tab_view,
        $changeset_view,
      );
    }

    if ($comment_form) {
      $footer[] = $comment_form;
    } else {
      // TODO: For now, just use this to get "Login to Comment".
      $footer[] = id(new PhabricatorApplicationTransactionCommentView())
        ->setUser($viewer)
        ->setRequestURI($request->getRequestURI());
    }

    $object_id = 'D'.$revision->getID();
    $operations_box = $this->buildOperationsBox($revision);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($object_id, '/'.$object_id);
    $crumbs->setBorder(true);

    $filetree_on = $viewer->compareUserSetting(
      PhabricatorShowFiletreeSetting::SETTINGKEY,
      PhabricatorShowFiletreeSetting::VALUE_ENABLE_FILETREE);

    $nav = null;
    if ($filetree_on) {
      $collapsed_key = PhabricatorFiletreeVisibleSetting::SETTINGKEY;
      $collapsed_value = $viewer->getUserSetting($collapsed_key);

      $nav = id(new DifferentialChangesetFileTreeSideNavBuilder())
        ->setTitle('D'.$revision->getID())
        ->setBaseURI(new PhutilURI('/D'.$revision->getID()))
        ->setCollapsed((bool)$collapsed_value)
        ->build($changesets);
    }

    // Haunt Mode
    $pane_id = celerity_generate_unique_node_id();
    Javelin::initBehavior(
      'differential-keyboard-navigation',
      array(
        'haunt' => $pane_id,
      ));
    Javelin::initBehavior('differential-user-select');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setSubheader($subheader)
      ->setCurtain($curtain)
      ->setID($pane_id)
      ->setMainColumn(array(
        $operations_box,
        $info_view,
        $details,
        $diff_detail_box,
        $unit_box,
        $comment_view,
        $signature_message,
      ))
      ->setFooter($footer);

    $page =  $this->newPage()
      ->setTitle($object_id.' '.$revision->getTitle())
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(array($revision->getPHID()))
      ->appendChild($view);

    if ($nav) {
      $page->setNavigation($nav);
    }

    return $page;
  }

  private function buildHeader(DifferentialRevision $revision) {
    $view = id(new PHUIHeaderView())
      ->setHeader($revision->getTitle($revision))
      ->setUser($this->getViewer())
      ->setPolicyObject($revision)
      ->setHeaderIcon('fa-cog');

    $status = $revision->getStatus();
    $status_name =
      DifferentialRevisionStatus::renderFullDescription($status);

    $view->addProperty(PHUIHeaderView::PROPERTY_STATUS, $status_name);

    return $view;
  }

  private function buildSubheaderView(DifferentialRevision $revision) {
    $viewer = $this->getViewer();

    $author_phid = $revision->getAuthorPHID();

    $author = $viewer->renderHandle($author_phid)->render();
    $date = phabricator_datetime($revision->getDateCreated(), $viewer);
    $author = phutil_tag('strong', array(), $author);

    $handles = $viewer->loadHandles(array($author_phid));
    $image_uri = $handles[$author_phid]->getImageURI();
    $image_href = $handles[$author_phid]->getURI();

    $content = pht('Authored by %s on %s.', $author, $date);

    return id(new PHUIHeadThingView())
      ->setImage($image_uri)
      ->setImageHref($image_href)
      ->setContent($content);
  }

  private function buildDetails(
    DifferentialRevision $revision,
    $custom_fields) {
    $viewer = $this->getViewer();
    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    if ($custom_fields) {
      $custom_fields->appendFieldsToPropertyList(
        $revision,
        $viewer,
        $properties);
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Details'));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($properties);
  }

  private function buildCurtain(DifferentialRevision $revision) {
    $viewer = $this->getViewer();
    $revision_id = $revision->getID();
    $revision_phid = $revision->getPHID();
    $curtain = $this->newCurtainView($revision);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $revision,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setHref("/differential/revision/edit/{$revision_id}/")
        ->setName(pht('Edit Revision'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-upload')
        ->setHref("/differential/revision/update/{$revision_id}/")
        ->setName(pht('Update Diff'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $request_uri = $this->getRequest()->getRequestURI();
    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-download')
        ->setName(pht('Download Raw Diff'))
        ->setHref($request_uri->alter('download', 'true')));

    $relationship_list = PhabricatorObjectRelationshipList::newForObject(
      $viewer,
      $revision);

    $revision_actions = array(
      DifferentialRevisionHasParentRelationship::RELATIONSHIPKEY,
      DifferentialRevisionHasChildRelationship::RELATIONSHIPKEY,
    );

    $revision_submenu = $relationship_list->newActionSubmenu($revision_actions)
      ->setName(pht('Edit Related Revisions...'))
      ->setIcon('fa-cog');

    $curtain->addAction($revision_submenu);

    $relationship_submenu = $relationship_list->newActionMenu();
    if ($relationship_submenu) {
      $curtain->addAction($relationship_submenu);
    }

    return $curtain;
  }

  private function buildCommentForm(
    DifferentialRevision $revision,
    $field_list) {

    $viewer = $this->getViewer();

    $draft = id(new PhabricatorDraft())->loadOneWhere(
      'authorPHID = %s AND draftKey = %s',
      $viewer->getPHID(),
      'differential-comment-'.$revision->getID());

    $reviewers = array();
    $ccs = array();
    if ($draft) {
      $reviewers = idx($draft->getMetadata(), 'reviewers', array());
      $ccs = idx($draft->getMetadata(), 'ccs', array());
      if ($reviewers || $ccs) {
        $handles = $this->loadViewerHandles(array_merge($reviewers, $ccs));
        $reviewers = array_select_keys($handles, $reviewers);
        $ccs = array_select_keys($handles, $ccs);
      }
    }

    $comment_form = id(new DifferentialAddCommentView())
      ->setRevision($revision);

    $review_warnings = array();
    foreach ($field_list->getFields() as $field) {
      $review_warnings[] = $field->getWarningsForDetailView();
    }
    $review_warnings = array_mergev($review_warnings);

    if ($review_warnings) {
      $review_warnings_panel = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors($review_warnings);
      $comment_form->setInfoView($review_warnings_panel);
    }

    $action_uri = $this->getApplicationURI(
      'comment/save/'.$revision->getID().'/');

    $comment_form->setActions($this->getRevisionCommentActions($revision))
      ->setActionURI($action_uri)
      ->setUser($viewer)
      ->setDraft($draft)
      ->setReviewers(mpull($reviewers, 'getFullName', 'getPHID'))
      ->setCCs(mpull($ccs, 'getFullName', 'getPHID'));

    // TODO: This just makes the "Z" key work. Generalize this and remove
    // it at some point.
    $comment_form = phutil_tag(
      'div',
      array(
        'class' => 'differential-add-comment-panel',
      ),
      $comment_form);
    return $comment_form;
  }

  private function getRevisionCommentActions(DifferentialRevision $revision) {
    $actions = array(
      DifferentialAction::ACTION_COMMENT => true,
    );

    $viewer = $this->getViewer();
    $viewer_phid = $viewer->getPHID();
    $viewer_is_owner = ($viewer_phid == $revision->getAuthorPHID());
    $viewer_is_reviewer = in_array($viewer_phid, $revision->getReviewers());
    $status = $revision->getStatus();

    $viewer_has_accepted = false;
    $viewer_has_rejected = false;
    $status_accepted = DifferentialReviewerStatus::STATUS_ACCEPTED;
    $status_rejected = DifferentialReviewerStatus::STATUS_REJECTED;
    foreach ($revision->getReviewerStatus() as $reviewer) {
      if ($reviewer->getReviewerPHID() == $viewer_phid) {
        if ($reviewer->getStatus() == $status_accepted) {
          $viewer_has_accepted = true;
        }
        if ($reviewer->getStatus() == $status_rejected) {
          $viewer_has_rejected = true;
        }
        break;
      }
    }

    $allow_self_accept = PhabricatorEnv::getEnvConfig(
      'differential.allow-self-accept');
    $always_allow_abandon = PhabricatorEnv::getEnvConfig(
      'differential.always-allow-abandon');
    $always_allow_close = PhabricatorEnv::getEnvConfig(
      'differential.always-allow-close');
    $allow_reopen = PhabricatorEnv::getEnvConfig(
      'differential.allow-reopen');

    if ($viewer_is_owner) {
      switch ($status) {
        case ArcanistDifferentialRevisionStatus::NEEDS_REVIEW:
          $actions[DifferentialAction::ACTION_ACCEPT] = $allow_self_accept;
          $actions[DifferentialAction::ACTION_ABANDON] = true;
          $actions[DifferentialAction::ACTION_RETHINK] = true;
          break;
        case ArcanistDifferentialRevisionStatus::NEEDS_REVISION:
        case ArcanistDifferentialRevisionStatus::CHANGES_PLANNED:
          $actions[DifferentialAction::ACTION_ACCEPT] = $allow_self_accept;
          $actions[DifferentialAction::ACTION_ABANDON] = true;
          $actions[DifferentialAction::ACTION_REQUEST] = true;
          break;
        case ArcanistDifferentialRevisionStatus::ACCEPTED:
          $actions[DifferentialAction::ACTION_ABANDON] = true;
          $actions[DifferentialAction::ACTION_REQUEST] = true;
          $actions[DifferentialAction::ACTION_RETHINK] = true;
          $actions[DifferentialAction::ACTION_CLOSE] = true;
          break;
        case ArcanistDifferentialRevisionStatus::CLOSED:
          break;
        case ArcanistDifferentialRevisionStatus::ABANDONED:
          $actions[DifferentialAction::ACTION_RECLAIM] = true;
          break;
      }
    } else {
      switch ($status) {
        case ArcanistDifferentialRevisionStatus::NEEDS_REVIEW:
          $actions[DifferentialAction::ACTION_ABANDON] = $always_allow_abandon;
          $actions[DifferentialAction::ACTION_ACCEPT] = true;
          $actions[DifferentialAction::ACTION_REJECT] = true;
          $actions[DifferentialAction::ACTION_RESIGN] = $viewer_is_reviewer;
          break;
        case ArcanistDifferentialRevisionStatus::NEEDS_REVISION:
        case ArcanistDifferentialRevisionStatus::CHANGES_PLANNED:
          $actions[DifferentialAction::ACTION_ABANDON] = $always_allow_abandon;
          $actions[DifferentialAction::ACTION_ACCEPT] = true;
          $actions[DifferentialAction::ACTION_REJECT] = !$viewer_has_rejected;
          $actions[DifferentialAction::ACTION_RESIGN] = $viewer_is_reviewer;
          break;
        case ArcanistDifferentialRevisionStatus::ACCEPTED:
          $actions[DifferentialAction::ACTION_ABANDON] = $always_allow_abandon;
          $actions[DifferentialAction::ACTION_ACCEPT] = !$viewer_has_accepted;
          $actions[DifferentialAction::ACTION_REJECT] = true;
          $actions[DifferentialAction::ACTION_RESIGN] = $viewer_is_reviewer;
          break;
        case ArcanistDifferentialRevisionStatus::CLOSED:
        case ArcanistDifferentialRevisionStatus::ABANDONED:
          break;
      }
      if ($status != ArcanistDifferentialRevisionStatus::CLOSED) {
        $actions[DifferentialAction::ACTION_CLAIM] = true;
        $actions[DifferentialAction::ACTION_CLOSE] = $always_allow_close;
      }
    }

    $actions[DifferentialAction::ACTION_ADDREVIEWERS] = true;
    $actions[DifferentialAction::ACTION_ADDCCS] = true;
    $actions[DifferentialAction::ACTION_REOPEN] = $allow_reopen &&
      ($status == ArcanistDifferentialRevisionStatus::CLOSED);

    $actions = array_keys(array_filter($actions));
    $actions_dict = array();
    foreach ($actions as $action) {
      $actions_dict[$action] = DifferentialAction::getActionVerb($action);
    }

    return $actions_dict;
  }

  private function loadHistoryDiffStatus(array $diffs) {
    assert_instances_of($diffs, 'DifferentialDiff');

    $diff_phids = mpull($diffs, 'getPHID');
    $bad_unit_status = array(
      ArcanistUnitTestResult::RESULT_FAIL,
      ArcanistUnitTestResult::RESULT_BROKEN,
    );

    $message = new HarbormasterBuildUnitMessage();
    $target = new HarbormasterBuildTarget();
    $build = new HarbormasterBuild();
    $buildable = new HarbormasterBuildable();

    $broken_diffs = queryfx_all(
      $message->establishConnection('r'),
      'SELECT distinct a.buildablePHID
        FROM %T m
          JOIN %T t ON m.buildTargetPHID = t.phid
          JOIN %T b ON t.buildPHID = b.phid
          JOIN %T a ON b.buildablePHID = a.phid
        WHERE a.buildablePHID IN (%Ls)
          AND m.result in (%Ls)',
      $message->getTableName(),
      $target->getTableName(),
      $build->getTableName(),
      $buildable->getTableName(),
      $diff_phids,
      $bad_unit_status);

    $unit_status = array();
    foreach ($broken_diffs as $broken) {
      $phid = $broken['buildablePHID'];
      $unit_status[$phid] = DifferentialUnitStatus::UNIT_FAIL;
    }

    return $unit_status;
  }

  private function loadChangesetsAndVsMap(
    DifferentialDiff $target,
    DifferentialDiff $diff_vs = null,
    PhabricatorRepository $repository = null) {

    $load_diffs = array($target);
    if ($diff_vs) {
      $load_diffs[] = $diff_vs;
    }

    $raw_changesets = id(new DifferentialChangesetQuery())
      ->setViewer($this->getRequest()->getUser())
      ->withDiffs($load_diffs)
      ->execute();
    $changeset_groups = mgroup($raw_changesets, 'getDiffID');

    $changesets = idx($changeset_groups, $target->getID(), array());
    $changesets = mpull($changesets, null, 'getID');

    $refs          = array();
    $vs_map        = array();
    $vs_changesets = array();
    if ($diff_vs) {
      $vs_id                  = $diff_vs->getID();
      $vs_changesets_path_map = array();
      foreach (idx($changeset_groups, $vs_id, array()) as $changeset) {
        $path = $changeset->getAbsoluteRepositoryPath($repository, $diff_vs);
        $vs_changesets_path_map[$path] = $changeset;
        $vs_changesets[$changeset->getID()] = $changeset;
      }
      foreach ($changesets as $key => $changeset) {
        $path = $changeset->getAbsoluteRepositoryPath($repository, $target);
        if (isset($vs_changesets_path_map[$path])) {
          $vs_map[$changeset->getID()] =
            $vs_changesets_path_map[$path]->getID();
          $refs[$changeset->getID()] =
            $changeset->getID().'/'.$vs_changesets_path_map[$path]->getID();
          unset($vs_changesets_path_map[$path]);
        } else {
          $refs[$changeset->getID()] = $changeset->getID();
        }
      }
      foreach ($vs_changesets_path_map as $path => $changeset) {
        $changesets[$changeset->getID()] = $changeset;
        $vs_map[$changeset->getID()]     = -1;
        $refs[$changeset->getID()]       = $changeset->getID().'/-1';
      }
    } else {
      foreach ($changesets as $changeset) {
        $refs[$changeset->getID()] = $changeset->getID();
      }
    }

    $changesets = msort($changesets, 'getSortKey');

    return array($changesets, $vs_map, $vs_changesets, $refs);
  }

  private function buildSymbolIndexes(
    PhabricatorRepository $repository,
    array $visible_changesets) {
    assert_instances_of($visible_changesets, 'DifferentialChangeset');

    $engine = PhabricatorSyntaxHighlighter::newEngine();

    $langs = $repository->getSymbolLanguages();
    $langs = nonempty($langs, array());

    $sources = $repository->getSymbolSources();
    $sources = nonempty($sources, array());

    $symbol_indexes = array();

    if ($langs && $sources) {
      $have_symbols = id(new DiffusionSymbolQuery())
          ->existsSymbolsInRepository($repository->getPHID());
      if (!$have_symbols) {
        return $symbol_indexes;
      }
    }

    $repository_phids = array_merge(
      array($repository->getPHID()),
      $sources);

    $indexed_langs = array_fill_keys($langs, true);
    foreach ($visible_changesets as $key => $changeset) {
      $lang = $engine->getLanguageFromFilename($changeset->getFilename());
      if (empty($indexed_langs) || isset($indexed_langs[$lang])) {
        $symbol_indexes[$key] = array(
          'lang'         => $lang,
          'repositories' => $repository_phids,
        );
      }
    }

    return $symbol_indexes;
  }

  private function loadOtherRevisions(
    array $changesets,
    DifferentialDiff $target,
    PhabricatorRepository $repository) {
    assert_instances_of($changesets, 'DifferentialChangeset');

    $paths = array();
    foreach ($changesets as $changeset) {
      $paths[] = $changeset->getAbsoluteRepositoryPath(
        $repository,
        $target);
    }

    if (!$paths) {
      return array();
    }

    $path_map = id(new DiffusionPathIDQuery($paths))->loadPathIDs();

    if (!$path_map) {
      return array();
    }

    $recent = (PhabricatorTime::getNow() - phutil_units('30 days in seconds'));

    $query = id(new DifferentialRevisionQuery())
      ->setViewer($this->getRequest()->getUser())
      ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
      ->withUpdatedEpochBetween($recent, null)
      ->setOrder(DifferentialRevisionQuery::ORDER_MODIFIED)
      ->setLimit(10)
      ->needFlags(true)
      ->needDrafts(true)
      ->needRelationships(true);

    foreach ($path_map as $path => $path_id) {
      $query->withPath($repository->getID(), $path_id);
    }

    $results = $query->execute();

    // Strip out *this* revision.
    foreach ($results as $key => $result) {
      if ($result->getID() == $this->revisionID) {
        unset($results[$key]);
      }
    }

    return $results;
  }

  private function renderOtherRevisions(array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');
    $viewer = $this->getViewer();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Recent Similar Revisions'));

    $view = id(new DifferentialRevisionListView())
      ->setRevisions($revisions)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setNoBox(true)
      ->setUser($viewer);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    return $view;
  }


  /**
   * Note this code is somewhat similar to the buildPatch method in
   * @{class:DifferentialReviewRequestMail}.
   *
   * @return @{class:AphrontRedirectResponse}
   */
  private function buildRawDiffResponse(
    DifferentialRevision $revision,
    array $changesets,
    array $vs_changesets,
    array $vs_map,
    PhabricatorRepository $repository = null) {

    assert_instances_of($changesets,    'DifferentialChangeset');
    assert_instances_of($vs_changesets, 'DifferentialChangeset');

    $viewer = $this->getViewer();

    id(new DifferentialHunkQuery())
      ->setViewer($viewer)
      ->withChangesets($changesets)
      ->needAttachToChangesets(true)
      ->execute();

    $diff = new DifferentialDiff();
    $diff->attachChangesets($changesets);
    $raw_changes = $diff->buildChangesList();
    $changes = array();
    foreach ($raw_changes as $changedict) {
      $changes[] = ArcanistDiffChange::newFromDictionary($changedict);
    }

    $loader = id(new PhabricatorFileBundleLoader())
      ->setViewer($viewer);

    $bundle = ArcanistBundle::newFromChanges($changes);
    $bundle->setLoadFileDataCallback(array($loader, 'loadFileData'));

    $vcs = $repository ? $repository->getVersionControlSystem() : null;
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $raw_diff = $bundle->toGitPatch();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
      default:
        $raw_diff = $bundle->toUnifiedDiff();
        break;
    }

    $request_uri = $this->getRequest()->getRequestURI();

    // this ends up being something like
    //   D123.diff
    // or the verbose
    //   D123.vs123.id123.whitespaceignore-all.diff
    // lame but nice to include these options
    $file_name = ltrim($request_uri->getPath(), '/').'.';
    foreach ($request_uri->getQueryParams() as $key => $value) {
      if ($key == 'download') {
        continue;
      }
      $file_name .= $key.$value.'.';
    }
    $file_name .= 'diff';

    $file = PhabricatorFile::buildFromFileDataOrHash(
      $raw_diff,
      array(
        'name' => $file_name,
        'ttl' => (60 * 60 * 24),
        'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
      ));

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $file->attachToObject($revision->getPHID());
    unset($unguarded);

    return $file->getRedirectResponse();
  }

  private function buildTransactions(
    DifferentialRevision $revision,
    DifferentialDiff $left_diff,
    DifferentialDiff $right_diff,
    array $old_ids,
    array $new_ids) {

    $timeline = $this->buildTransactionTimeline(
      $revision,
      new DifferentialTransactionQuery(),
      $engine = null,
      array(
        'left' => $left_diff->getID(),
        'right' => $right_diff->getID(),
        'old' => implode(',', $old_ids),
        'new' => implode(',', $new_ids),
      ));

    return $timeline;
  }

  private function buildRevisionWarnings(
    DifferentialRevision $revision,
    PhabricatorCustomFieldList $field_list,
    array $warning_handle_map,
    array $handles) {

    $warnings = array();
    foreach ($field_list->getFields() as $key => $field) {
      $phids = idx($warning_handle_map, $key, array());
      $field_handles = array_select_keys($handles, $phids);
      $field_warnings = $field->getWarningsForRevisionHeader($field_handles);
      foreach ($field_warnings as $warning) {
        $warnings[] = $warning;
      }
    }

    return $warnings;
  }

  private function buildDiffDetailView(
    array $diffs,
    DifferentialRevision $revision,
    PhabricatorCustomFieldList $field_list) {
    $viewer = $this->getViewer();

    $fields = array();
    foreach ($field_list->getFields() as $field) {
      if ($field->shouldAppearInDiffPropertyView()) {
        $fields[] = $field;
      }
    }

    if (!$fields) {
      return null;
    }

    $property_lists = array();
    foreach ($this->getDiffTabLabels($diffs) as $tab) {
      list($label, $diff) = $tab;

      $property_lists[] = array(
        $label,
        $this->buildDiffPropertyList($diff, $revision, $fields),
      );
    }

    $tab_group = id(new PHUITabGroupView())
      ->setHideSingleTab(true);

    foreach ($property_lists as $key => $property_list) {
      list($tab_name, $list_view) = $property_list;

      $tab = id(new PHUITabView())
        ->setKey($key)
        ->setName($tab_name)
        ->appendChild($list_view);

      $tab_group->addTab($tab);
      $tab_group->selectTab($key);
    }

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Diff Detail'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setUser($viewer)
      ->addTabGroup($tab_group);
  }

  private function buildDiffPropertyList(
    DifferentialDiff $diff,
    DifferentialRevision $revision,
    array $fields) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($diff);

    foreach ($fields as $field) {
      $label = $field->renderDiffPropertyViewLabel($diff);
      $value = $field->renderDiffPropertyViewValue($diff);
      if ($value !== null) {
        $view->addProperty($label, $value);
      }
    }

    return $view;
  }

  private function buildOperationsBox(DifferentialRevision $revision) {
    $viewer = $this->getViewer();

    // Save a query if we can't possibly have pending operations.
    $repository = $revision->getRepository();
    if (!$repository || !$repository->canPerformAutomation()) {
      return null;
    }

    $operations = id(new DrydockRepositoryOperationQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($revision->getPHID()))
      ->withIsDismissed(false)
      ->withOperationTypes(
        array(
          DrydockLandRepositoryOperation::OPCONST,
        ))
      ->execute();
    if (!$operations) {
      return null;
    }

    $state_fail = DrydockRepositoryOperation::STATE_FAIL;

    // We're going to show the oldest operation which hasn't failed, or the
    // most recent failure if they're all failures.
    $operations = msort($operations, 'getID');
    foreach ($operations as $operation) {
      if ($operation->getOperationState() != $state_fail) {
        break;
      }
    }

    // If we found a completed operation, don't render anything. We don't want
    // to show an older error after the thing worked properly.
    if ($operation->isDone()) {
      return null;
    }

    $box_view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Active Operations'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);

    return id(new DrydockRepositoryOperationStatusView())
      ->setUser($viewer)
      ->setBoxView($box_view)
      ->setOperation($operation);
  }

  private function buildUnitMessagesView(
    $diff,
    DifferentialRevision $revision) {
    $viewer = $this->getViewer();

    if (!$diff->getBuildable()) {
      return null;
    }

    if (!$diff->getUnitMessages()) {
      return null;
    }

    $interesting_messages = array();
    foreach ($diff->getUnitMessages() as $message) {
      switch ($message->getResult()) {
        case ArcanistUnitTestResult::RESULT_PASS:
        case ArcanistUnitTestResult::RESULT_SKIP:
          break;
        default:
          $interesting_messages[] = $message;
          break;
      }
    }

    if (!$interesting_messages) {
      return null;
    }

    $excuse = null;
    if ($diff->hasDiffProperty('arc:unit-excuse')) {
      $excuse = $diff->getProperty('arc:unit-excuse');
    }

    return id(new HarbormasterUnitSummaryView())
      ->setUser($viewer)
      ->setExcuse($excuse)
      ->setBuildable($diff->getBuildable())
      ->setUnitMessages($diff->getUnitMessages())
      ->setLimit(5)
      ->setShowViewAll(true);
  }

}
