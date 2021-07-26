<?php

final class DifferentialRevisionViewController
  extends DifferentialController {

  private $revisionID;
  private $changesetCount;
  private $hiddenChangesets;
  private $warnings = array();

  public function shouldAllowPublic() {
    return true;
  }

  public function isLargeDiff() {
    return ($this->getChangesetCount() > $this->getLargeDiffLimit());
  }

  public function isVeryLargeDiff() {
    return ($this->getChangesetCount() > $this->getVeryLargeDiffLimit());
  }

  public function getLargeDiffLimit() {
    return 100;
  }

  public function getVeryLargeDiffLimit() {
    return 1000;
  }

  public function getChangesetCount() {
    if ($this->changesetCount === null) {
      throw new PhutilInvalidStateException('setChangesetCount');
    }
    return $this->changesetCount;
  }

  public function setChangesetCount($count) {
    $this->changesetCount = $count;
    return $this;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $this->revisionID = $request->getURIData('id');

    $viewer_is_anonymous = !$viewer->isLoggedIn();

    $revision = id(new DifferentialRevisionQuery())
      ->withIDs(array($this->revisionID))
      ->setViewer($viewer)
      ->needReviewers(true)
      ->needReviewerAuthority(true)
      ->needCommitPHIDs(true)
      ->executeOne();
    if (!$revision) {
      return new Aphront404Response();
    }

    $diffs = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withRevisionIDs(array($this->revisionID))
      ->execute();
    $diffs = array_reverse($diffs, $preserve_keys = true);

    if (!$diffs) {
      throw new Exception(
        pht('This revision has no diffs. Something has gone quite wrong.'));
    }

    $revision->attachActiveDiff(last($diffs));

    $diff_vs = $this->getOldDiffID($revision, $diffs);
    if ($diff_vs instanceof AphrontResponse) {
      return $diff_vs;
    }

    $target_id = $this->getNewDiffID($revision, $diffs);
    if ($target_id instanceof AphrontResponse) {
      return $target_id;
    }

    $target = $diffs[$target_id];

    $target_manual = $target;
    if (!$target_id) {
      foreach ($diffs as $diff) {
        if ($diff->getCreationMethod() != 'commit') {
          $target_manual = $diff;
        }
      }
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

    $this->setChangesetCount(count($rendering_references));

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

    $subscriber_phids = PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $revision->getPHID());

    $object_phids = array_merge(
      $revision->getReviewerPHIDs(),
      $subscriber_phids,
      $revision->getCommitPHIDs(),
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
    $warnings = $this->warnings;

    $request_uri = $request->getRequestURI();

    $large = $request->getStr('large');

    $large_warning =
      ($this->isLargeDiff()) &&
      (!$this->isVeryLargeDiff()) &&
      (!$large);

    if ($large_warning) {
      $count = $this->getChangesetCount();

      $expand_uri = $request_uri
        ->alter('large', 'true')
        ->setFragment('toc');

      $message = array(
        pht(
          'This large diff affects %s files. Files without inline '.
          'comments have been collapsed.',
          new PhutilNumber($count)),
        ' ',
        phutil_tag(
          'strong',
          array(),
          phutil_tag(
            'a',
            array(
              'href' => $expand_uri,
            ),
            pht('Expand All Files'))),
      );

      $warnings[] = id(new PHUIInfoView())
        ->setTitle(pht('Large Diff'))
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->appendChild($message);

      $folded_changesets = $changesets;
    } else {
      $folded_changesets = array();
    }

    // Don't hide or fold changesets which have inline comments.
    $hidden_changesets = $this->hiddenChangesets;
    if ($hidden_changesets || $folded_changesets) {
      $old = array_select_keys($changesets, $old_ids);
      $new = array_select_keys($changesets, $new_ids);

      $inlines = id(new DifferentialDiffInlineCommentQuery())
        ->setViewer($viewer)
        ->withRevisionPHIDs(array($revision->getPHID()))
        ->withPublishableComments(true)
        ->withPublishedComments(true)
        ->execute();

      $inlines = mpull($inlines, 'newInlineCommentObject');

      $inlines = id(new PhabricatorInlineCommentAdjustmentEngine())
        ->setViewer($viewer)
        ->setRevision($revision)
        ->setOldChangesets($old)
        ->setNewChangesets($new)
        ->setInlines($inlines)
        ->execute();

      foreach ($inlines as $inline) {
        $changeset_id = $inline->getChangesetID();
        if (!isset($changesets[$changeset_id])) {
          continue;
        }

        unset($hidden_changesets[$changeset_id]);
        unset($folded_changesets[$changeset_id]);
      }
    }

    // If we would hide only one changeset, don't hide anything. The notice
    // we'd render about it is about the same size as the changeset.
    if (count($hidden_changesets) < 2) {
      $hidden_changesets = array();
    }

    // Update the set of hidden changesets, since we may have just un-hidden
    // some of them.
    if ($hidden_changesets) {
      $warnings[] = id(new PHUIInfoView())
        ->setTitle(pht('Showing Only Differences'))
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->appendChild(
          pht(
            'This revision modifies %s more files that are hidden because '.
            'they were not modified between selected diffs and they have no '.
            'inline comments.',
            phutil_count($hidden_changesets)));
    }

    // Compute the unfolded changesets. By default, everything is unfolded.
    $unfolded_changesets = $changesets;
    foreach ($folded_changesets as $changeset_id => $changeset) {
      unset($unfolded_changesets[$changeset_id]);
    }

    // Throw away any hidden changesets.
    foreach ($hidden_changesets as $changeset_id => $changeset) {
      unset($changesets[$changeset_id]);
      unset($unfolded_changesets[$changeset_id]);
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

    $repository = $revision->getRepository();
    if ($repository) {
      $symbol_indexes = $this->buildSymbolIndexes(
        $repository,
        $unfolded_changesets);
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

    $timeline = $this->buildTransactions(
      $revision,
      $diff_vs ? $diffs[$diff_vs] : $target,
      $target,
      $old_ids,
      $new_ids);

    $timeline->setQuoteRef($revision->getMonogram());

    if ($this->isVeryLargeDiff()) {
      $messages = array();

      $messages[] = pht(
        'This very large diff affects more than %s files. Use the %s to '.
        'browse changes.',
        new PhutilNumber($this->getVeryLargeDiffLimit()),
        phutil_tag(
          'a',
          array(
            'href' => '/differential/diff/'.$target->getID().'/changesets/',
          ),
          phutil_tag('strong', array(), pht('Changeset List'))));

      $changeset_view = id(new PHUIInfoView())
        ->setErrors($messages);
    } else {
      $changeset_view = id(new DifferentialChangesetListView())
        ->setChangesets($changesets)
        ->setVisibleChangesets($unfolded_changesets)
        ->setStandaloneURI('/differential/changeset/')
        ->setRawFileURIs(
          '/differential/changeset/?view=old',
          '/differential/changeset/?view=new')
        ->setUser($viewer)
        ->setDiff($target)
        ->setRenderingReferences($rendering_references)
        ->setVsMap($vs_map)
        ->setSymbolIndexes($symbol_indexes)
        ->setTitle(pht('Diff %s', $target->getID()))
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);

      $revision_id = $revision->getID();
      $inline_list_uri = "/revision/inlines/{$revision_id}/";
      $inline_list_uri = $this->getApplicationURI($inline_list_uri);
      $changeset_view->setInlineListURI($inline_list_uri);

      if ($repository) {
        $changeset_view->setRepository($repository);
      }

      if (!$viewer_is_anonymous) {
        $changeset_view->setInlineCommentControllerURI(
          '/differential/comment/inline/edit/'.$revision->getID().'/');
      }
    }

    $broken_diffs = $this->loadHistoryDiffStatus($diffs);

    $history = id(new DifferentialRevisionUpdateHistoryView())
      ->setUser($viewer)
      ->setDiffs($diffs)
      ->setDiffUnitStatuses($broken_diffs)
      ->setSelectedVersusDiffID($diff_vs)
      ->setSelectedDiffID($target->getID())
      ->setCommitsForLinks($commits_for_links);

    $local_table = id(new DifferentialLocalCommitsView())
      ->setUser($viewer)
      ->setLocalCommits(idx($props, 'local:commits'))
      ->setCommitsForLinks($commits_for_links);

    if ($repository && !$this->isVeryLargeDiff()) {
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

    if ($this->isVeryLargeDiff()) {
      $toc_view = null;

      // When rendering a "very large" diff, we skip computation of owners
      // that own no files because it is significantly expensive and not very
      // valuable.
      foreach ($revision->getReviewers() as $reviewer) {
        // Give each reviewer a dummy nonempty value so the UI does not render
        // the "(Owns No Changed Paths)" note. If that behavior becomes more
        // sophisticated in the future, this behavior might also need to.
        $reviewer->attachChangesets($changesets);
      }
    } else {
      $this->buildPackageMaps($changesets);

      $toc_view = $this->buildTableOfContents(
        $changesets,
        $unfolded_changesets,
        $target->loadCoverageMap($viewer));

      // Attach changesets to each reviewer so we can show which Owners package
      // reviewers own no files.
      foreach ($revision->getReviewers() as $reviewer) {
        $reviewer_phid = $reviewer->getReviewerPHID();
        $reviewer_changesets = $this->getPackageChangesets($reviewer_phid);
        $reviewer->attachChangesets($reviewer_changesets);
      }

      $authority_packages = $this->getAuthorityPackages();
      foreach ($changesets as $changeset) {
        $changeset_packages = $this->getChangesetPackages($changeset);

        $changeset
          ->setAuthorityPackages($authority_packages)
          ->setChangesetPackages($changeset_packages);
      }
    }

    $tab_group = new PHUITabGroupView();

    if ($toc_view) {
      $tab_group->addTab(
        id(new PHUITabView())
          ->setName(pht('Files'))
          ->setKey('files')
          ->appendChild($toc_view));
    }

    $tab_group->addTab(
      id(new PHUITabView())
        ->setName(pht('History'))
        ->setKey('history')
        ->appendChild($history));

    $filetree = id(new DifferentialFileTreeEngine())
      ->setViewer($viewer);
    $filetree_collapsed = !$filetree->getIsVisible();

    // See PHI811. If the viewer has the file tree on, the files tab with the
    // table of contents is redundant, so default to the "History" tab instead.
    if (!$filetree_collapsed) {
      $tab_group->selectTab('history');
    }

    $tab_group->addTab(
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
      // See PHI1900. The graph UI element now tries to figure out the correct
      // height automatically, but currently can't in this case because the
      // element is not visible when the page loads. Set an explicit height.
      $stack_graph->setHeight(34);

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

    $view_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Changeset List'))
      ->setHref('/differential/diff/'.$target->getID().'/changesets/')
      ->setIcon('fa-align-left');

    $tab_header = id(new PHUIHeaderView())
      ->setHeader(pht('Revision Contents'))
      ->addActionLink($view_button);

    $tab_view = id(new PHUIObjectBoxView())
      ->setHeader($tab_header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addTabGroup($tab_group);

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
        $warnings,
        $tab_view,
        $changeset_view,
      );
    }

    $comment_view = id(new DifferentialRevisionEditEngine())
      ->setViewer($viewer)
      ->buildEditEngineCommentView($revision);

    $comment_view->setTransactionTimeline($timeline);

    $review_warnings = array();
    foreach ($field_list->getFields() as $field) {
      $review_warnings[] = $field->getWarningsForDetailView();
    }
    $review_warnings = array_mergev($review_warnings);

    if ($review_warnings) {
      $warnings_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors($review_warnings);

      $comment_view->setInfoView($warnings_view);
    }

    $footer[] = $comment_view;

    $monogram = $revision->getMonogram();
    $operations_box = $this->buildOperationsBox($revision);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($monogram);
    $crumbs->setBorder(true);

    $filetree
      ->setChangesets($changesets)
      ->setDisabled($this->isVeryLargeDiff());

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setSubheader($subheader)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $operations_box,
          $info_view,
          $details,
          $diff_detail_box,
          $unit_box,
          $timeline,
          $signature_message,
        ))
      ->setFooter($footer);

    $main_content = array(
      $crumbs,
      $view,
    );

    $main_content = $filetree->newView($main_content);

    if (!$filetree->getDisabled()) {
      $changeset_view->setFormationView($main_content);
    }

    $page = $this->newPage()
      ->setTitle($monogram.' '.$revision->getTitle())
      ->setPageObjectPHIDs(array($revision->getPHID()))
      ->appendChild($main_content);

    return $page;
  }

  private function buildHeader(DifferentialRevision $revision) {
    $view = id(new PHUIHeaderView())
      ->setHeader($revision->getTitle($revision))
      ->setUser($this->getViewer())
      ->setPolicyObject($revision)
      ->setHeaderIcon('fa-cog');

    $status_tag = id(new PHUITagView())
      ->setName($revision->getStatusDisplayName())
      ->setIcon($revision->getStatusIcon())
      ->setColor($revision->getStatusTagColor())
      ->setType(PHUITagView::TYPE_SHADE);

    $view->addProperty(PHUIHeaderView::PROPERTY_STATUS, $status_tag);

    // If the revision is in a status other than "Draft", but not broadcasting,
    // add an additional "Draft" tag to the header to make it clear that this
    // revision hasn't promoted yet.
    if (!$revision->getShouldBroadcast() && !$revision->isDraft()) {
      $draft_status = DifferentialRevisionStatus::newForStatus(
        DifferentialRevisionStatus::DRAFT);

      $draft_tag = id(new PHUITagView())
        ->setName($draft_status->getDisplayName())
        ->setIcon($draft_status->getIcon())
        ->setColor($draft_status->getTagColor())
        ->setType(PHUITagView::TYPE_SHADE);

      $view->addTag($draft_tag);
    }

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

    $repository = $revision->getRepository();
    if ($repository && $repository->canPerformAutomation()) {
      $revision_id = $revision->getID();

      $op = new DrydockLandRepositoryOperation();
      $barrier = $op->getBarrierToLanding($viewer, $revision);

      if ($barrier) {
        $can_land = false;
      } else {
        $can_land = true;
      }

      $action = id(new PhabricatorActionView())
        ->setName(pht('Land Revision'))
        ->setIcon('fa-fighter-jet')
        ->setHref("/differential/revision/operation/{$revision_id}/")
        ->setWorkflow(true)
        ->setDisabled(!$can_land);

      $curtain->addAction($action);
    }

    return $curtain;
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
    $viewer = $this->getViewer();

    $load_diffs = array($target);
    if ($diff_vs) {
      $load_diffs[] = $diff_vs;
    }

    $raw_changesets = id(new DifferentialChangesetQuery())
      ->setViewer($viewer)
      ->withDiffs($load_diffs)
      ->execute();
    $changeset_groups = mgroup($raw_changesets, 'getDiffID');

    $changesets = idx($changeset_groups, $target->getID(), array());
    $changesets = mpull($changesets, null, 'getID');

    $refs = array();
    $vs_map = array();
    $vs_changesets = array();
    $must_compare = array();
    if ($diff_vs) {
      $vs_id = $diff_vs->getID();
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

          $must_compare[] = $changeset->getID();

        } else {
          $refs[$changeset->getID()] = $changeset->getID();
        }
      }

      foreach ($vs_changesets_path_map as $path => $changeset) {
        $changesets[$changeset->getID()] = $changeset;
        $vs_map[$changeset->getID()] = -1;
        $refs[$changeset->getID()] = $changeset->getID().'/-1';
      }

    } else {
      foreach ($changesets as $changeset) {
        $refs[$changeset->getID()] = $changeset->getID();
      }
    }

    $changesets = msort($changesets, 'getSortKey');

    // See T13137. When displaying the diff between two updates, hide any
    // changesets which haven't actually changed.
    $this->hiddenChangesets = array();
    foreach ($must_compare as $changeset_id) {
      $changeset = $changesets[$changeset_id];
      $vs_changeset = $vs_changesets[$vs_map[$changeset_id]];

      if ($changeset->hasSameEffectAs($vs_changeset)) {
        $this->hiddenChangesets[$changeset_id] = $changesets[$changeset_id];
      }
    }

    return array($changesets, $vs_map, $vs_changesets, $refs);
  }

  private function buildSymbolIndexes(
    PhabricatorRepository $repository,
    array $unfolded_changesets) {
    assert_instances_of($unfolded_changesets, 'DifferentialChangeset');

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
    foreach ($unfolded_changesets as $key => $changeset) {
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

    $viewer = $this->getViewer();

    $paths = array();
    foreach ($changesets as $changeset) {
      $paths[] = $changeset->getAbsoluteRepositoryPath(
        $repository,
        $target);
    }

    if (!$paths) {
      return array();
    }

    $recent = (PhabricatorTime::getNow() - phutil_units('30 days in seconds'));

    $query = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIsOpen(true)
      ->withUpdatedEpochBetween($recent, null)
      ->setOrder(DifferentialRevisionQuery::ORDER_MODIFIED)
      ->setLimit(10)
      ->needFlags(true)
      ->needDrafts(true)
      ->needReviewers(true)
      ->withRepositoryPHIDs(
        array(
          $repository->getPHID(),
        ))
      ->withPaths($paths);

    $results = $query->execute();

    // Strip out *this* revision.
    foreach ($results as $key => $result) {
      if ($result->getID() == $this->revisionID) {
       unset($results[$key]);
       break;
      }
    }

    return $results;
  }

  private function renderOtherRevisions(array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');
    $viewer = $this->getViewer();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Recent Similar Revisions'));

    return id(new DifferentialRevisionListView())
      ->setViewer($viewer)
      ->setRevisions($revisions)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setNoBox(true);
  }


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
    //   D123.vs123.id123.highlightjs.diff
    // lame but nice to include these options
    $file_name = ltrim($request_uri->getPath(), '/').'.';
    foreach ($request_uri->getQueryParamsAsPairList() as $pair) {
      list($key, $value) = $pair;
      if ($key == 'download') {
        continue;
      }
      $file_name .= $key.$value.'.';
    }
    $file_name .= 'diff';

    $iterator = new ArrayIterator(array($raw_diff));

    $source = id(new PhabricatorIteratorFileUploadSource())
      ->setName($file_name)
      ->setMIMEType('text/plain')
      ->setRelativeTTL(phutil_units('24 hours in seconds'))
      ->setViewPolicy(PhabricatorPolicies::POLICY_NOONE)
      ->setIterator($iterator);

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $file = $source->uploadFile();
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
    DifferentialDiff $diff,
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

    return id(new HarbormasterUnitSummaryView())
      ->setViewer($viewer)
      ->setBuildable($diff->getBuildable())
      ->setUnitMessages($diff->getUnitMessages())
      ->setLimit(5)
      ->setShowViewAll(true);
  }

  private function getOldDiffID(DifferentialRevision $revision, array $diffs) {
    assert_instances_of($diffs, 'DifferentialDiff');
    $request = $this->getRequest();

    $diffs = mpull($diffs, null, 'getID');

    $is_new = ($request->getURIData('filter') === 'new');
    $old_id = $request->getInt('vs');

    // This is ambiguous, so just 404 rather than trying to figure out what
    // the user expects.
    if ($is_new && $old_id) {
      return new Aphront404Response();
    }

    if ($is_new) {
      $viewer = $this->getViewer();

      $xactions = id(new DifferentialTransactionQuery())
        ->setViewer($viewer)
        ->withObjectPHIDs(array($revision->getPHID()))
        ->withAuthorPHIDs(array($viewer->getPHID()))
        ->setOrder('newest')
        ->setLimit(1)
        ->execute();

      if (!$xactions) {
        $this->warnings[] = id(new PHUIInfoView())
          ->setTitle(pht('No Actions'))
          ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
          ->appendChild(
            pht(
              'Showing all changes because you have never taken an '.
              'action on this revision.'));
      } else {
        $xaction = head($xactions);

        // Find the transactions which updated this revision. We want to
        // figure out which diff was active when you last took an action.
        $updates = id(new DifferentialTransactionQuery())
          ->setViewer($viewer)
          ->withObjectPHIDs(array($revision->getPHID()))
          ->withTransactionTypes(
            array(
              DifferentialRevisionUpdateTransaction::TRANSACTIONTYPE,
            ))
          ->setOrder('oldest')
          ->execute();

        // Sort the diffs into two buckets: those older than your last action
        // and those newer than your last action.
        $older = array();
        $newer = array();
        foreach ($updates as $update) {
          // If you updated the revision with "arc diff", try to count that
          // update as "before your last action".
          if ($update->getDateCreated() <= $xaction->getDateCreated()) {
            $older[] = $update->getNewValue();
          } else {
            $newer[] = $update->getNewValue();
          }
        }

        if (!$newer) {
          $this->warnings[] = id(new PHUIInfoView())
            ->setTitle(pht('No Recent Updates'))
            ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
            ->appendChild(
              pht(
                'Showing all changes because the diff for this revision '.
                'has not been updated since your last action.'));
        } else {
          $older = array_fuse($older);

          // Find the most recent diff from before the last action.
          $old = null;
          foreach ($diffs as $diff) {
            if (!isset($older[$diff->getPHID()])) {
              break;
            }

            $old = $diff;
          }

          // It's possible we may not find such a diff: transactions may have
          // been removed from the database, for example. If we miss, just
          // fail into some reasonable state since 404'ing would be perplexing.
          if ($old) {
            $this->warnings[] = id(new PHUIInfoView())
              ->setTitle(pht('New Changes Shown'))
              ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
              ->appendChild(
                pht(
                  'Showing changes since the last action you took on this '.
                  'revision.'));

            $old_id = $old->getID();
          }
        }
      }
    }

    if (isset($diffs[$old_id])) {
      return $old_id;
    }

    return null;
  }

  private function getNewDiffID(DifferentialRevision $revision, array $diffs) {
    assert_instances_of($diffs, 'DifferentialDiff');
    $request = $this->getRequest();

    $diffs = mpull($diffs, null, 'getID');

    $is_new = ($request->getURIData('filter') === 'new');
    $new_id = $request->getInt('id');

    if ($is_new && $new_id) {
      return new Aphront404Response();
    }

    if (isset($diffs[$new_id])) {
      return $new_id;
    }

    return (int)last_key($diffs);
  }

}
