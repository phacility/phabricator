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

    $props = id(new DifferentialDiffProperty())->loadAllWhere(
      'diffID = %d',
      $target_manual->getID());
    $props = mpull($props, 'getData', 'getName');

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

    $revision_detail = id(new DifferentialRevisionDetailView())
      ->setUser($viewer)
      ->setRevision($revision)
      ->setDiff(end($diffs))
      ->setCustomFields($field_list)
      ->setURI($request->getRequestURI());

    $actions = $this->getRevisionActions($revision);

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

    $revision_detail->setActions($actions);
    $revision_detail->setUser($viewer);

    $revision_detail_box = $revision_detail->render();

    $revision_warnings = $this->buildRevisionWarnings(
      $revision,
      $field_list,
      $warning_handle_map,
      $handles);
    if ($revision_warnings) {
      $revision_warnings = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors($revision_warnings);
      $revision_detail_box->setInfoView($revision_warnings);
    }

    $detail_diffs = array_select_keys(
      $diffs,
      array($diff_vs, $target->getID()));
    $detail_diffs = mpull($detail_diffs, null, 'getPHID');

    $buildables = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withBuildablePHIDs(array_keys($detail_diffs))
      ->withManualBuildables(false)
      ->needBuilds(true)
      ->needTargets(true)
      ->execute();
    $buildables = mpull($buildables, null, 'getBuildablePHID');
    foreach ($detail_diffs as $diff_phid => $detail_diff) {
      $detail_diff->attachBuildable(idx($buildables, $diff_phid));
    }

    $diff_detail_box = $this->buildDiffDetailView(
      $detail_diffs,
      $revision,
      $field_list);

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

    $wrap_id = celerity_generate_unique_node_id();
    $comment_view = phutil_tag(
      'div',
      array(
        'id' => $wrap_id,
      ),
      $comment_view);

    $changeset_view = new DifferentialChangesetListView();
    $changeset_view->setChangesets($changesets);
    $changeset_view->setVisibleChangesets($visible_changesets);

    if (!$viewer_is_anonymous) {
      $changeset_view->setInlineCommentControllerURI(
        '/differential/comment/inline/edit/'.$revision->getID().'/');
    }

    $changeset_view->setStandaloneURI('/differential/changeset/');
    $changeset_view->setRawFileURIs(
      '/differential/changeset/?view=old',
      '/differential/changeset/?view=new');

    $changeset_view->setUser($viewer);
    $changeset_view->setDiff($target);
    $changeset_view->setRenderingReferences($rendering_references);
    $changeset_view->setVsMap($vs_map);
    $changeset_view->setWhitespace($whitespace);
    if ($repository) {
      $changeset_view->setRepository($repository);
    }
    $changeset_view->setSymbolIndexes($symbol_indexes);
    $changeset_view->setTitle(pht('Diff %s', $target->getID()));

    $diff_history = id(new DifferentialRevisionUpdateHistoryView())
      ->setUser($viewer)
      ->setDiffs($diffs)
      ->setSelectedVersusDiffID($diff_vs)
      ->setSelectedDiffID($target->getID())
      ->setSelectedWhitespace($whitespace)
      ->setCommitsForLinks($commits_for_links);

    $local_view = id(new DifferentialLocalCommitsView())
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

    $comment_form = null;
    if (!$viewer_is_anonymous) {
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

      $comment_form = new DifferentialAddCommentView();
      $comment_form->setRevision($revision);

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

      $comment_form->setActions($this->getRevisionCommentActions($revision));
      $action_uri = $this->getApplicationURI(
        'comment/save/'.$revision->getID().'/');

      $comment_form->setActionURI($action_uri);
      $comment_form->setUser($viewer);
      $comment_form->setDraft($draft);
      $comment_form->setReviewers(mpull($reviewers, 'getFullName', 'getPHID'));
      $comment_form->setCCs(mpull($ccs, 'getFullName', 'getPHID'));

      // TODO: This just makes the "Z" key work. Generalize this and remove
      // it at some point.
      $comment_form = phutil_tag(
        'div',
        array(
          'class' => 'differential-add-comment-panel',
        ),
        $comment_form);
    }

    $pane_id = celerity_generate_unique_node_id();
    Javelin::initBehavior(
      'differential-keyboard-navigation',
      array(
        'haunt' => $pane_id,
      ));
    Javelin::initBehavior('differential-user-select');

    $page_pane = id(new DifferentialPrimaryPaneView())
      ->setID($pane_id)
      ->appendChild($comment_view);

    $signatures = DifferentialRequiredSignaturesField::loadForRevision(
      $revision);
    $missing_signatures = false;
    foreach ($signatures as $phid => $signed) {
      if (!$signed) {
        $missing_signatures = true;
      }
    }

    if ($missing_signatures) {
      $signature_message = id(new PHUIInfoView())
        ->setErrors(
          array(
            array(
              phutil_tag('strong', array(), pht('Content Hidden:')),
              ' ',
              pht(
                'The content of this revision is hidden until the author has '.
                'signed all of the required legal agreements.'),
            ),
          ));
      $page_pane->appendChild($signature_message);
    } else {
      $page_pane->appendChild(
        array(
          $diff_history,
          $warning,
          $local_view,
          $toc_view,
          $other_view,
          $changeset_view,
        ));
    }

    if ($comment_form) {
      $page_pane->appendChild($comment_form);
    } else {
      // TODO: For now, just use this to get "Login to Comment".
      $page_pane->appendChild(
        id(new PhabricatorApplicationTransactionCommentView())
          ->setUser($viewer)
          ->setRequestURI($request->getRequestURI()));
    }

    $object_id = 'D'.$revision->getID();

    $operations_box = $this->buildOperationsBox($revision);

    $content = array(
      $operations_box,
      $revision_detail_box,
      $diff_detail_box,
      $page_pane,
    );

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($object_id, '/'.$object_id);

    $prefs = $viewer->loadPreferences();

    $pref_filetree = PhabricatorUserPreferences::PREFERENCE_DIFF_FILETREE;
    if ($prefs->getPreference($pref_filetree)) {
      $collapsed = $prefs->getPreference(
        PhabricatorUserPreferences::PREFERENCE_NAV_COLLAPSED,
        false);

      $nav = id(new DifferentialChangesetFileTreeSideNavBuilder())
        ->setTitle('D'.$revision->getID())
        ->setBaseURI(new PhutilURI('/D'.$revision->getID()))
        ->setCollapsed((bool)$collapsed)
        ->build($changesets);
      $nav->appendChild($content);
      $nav->setCrumbs($crumbs);
      $content = $nav;
    } else {
      array_unshift($content, $crumbs);
    }

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => $object_id.' '.$revision->getTitle(),
        'pageObjects' => array($revision->getPHID()),
      ));
  }

  private function getRevisionActions(DifferentialRevision $revision) {
    $viewer = $this->getRequest()->getUser();
    $revision_id = $revision->getID();
    $revision_phid = $revision->getPHID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $revision,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions = array();

    $actions[] = id(new PhabricatorActionView())
      ->setIcon('fa-pencil')
      ->setHref("/differential/revision/edit/{$revision_id}/")
      ->setName(pht('Edit Revision'))
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    $actions[] = id(new PhabricatorActionView())
      ->setIcon('fa-upload')
      ->setHref("/differential/revision/update/{$revision_id}/")
      ->setName(pht('Update Diff'))
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    $this->requireResource('phabricator-object-selector-css');
    $this->requireResource('javelin-behavior-phabricator-object-selector');

    $actions[] = id(new PhabricatorActionView())
      ->setIcon('fa-link')
      ->setName(pht('Edit Dependencies'))
      ->setHref("/search/attach/{$revision_phid}/DREV/dependencies/")
      ->setWorkflow(true)
      ->setDisabled(!$can_edit);

    $maniphest = 'PhabricatorManiphestApplication';
    if (PhabricatorApplication::isClassInstalled($maniphest)) {
      $actions[] = id(new PhabricatorActionView())
        ->setIcon('fa-anchor')
        ->setName(pht('Edit Maniphest Tasks'))
        ->setHref("/search/attach/{$revision_phid}/TASK/")
        ->setWorkflow(true)
        ->setDisabled(!$can_edit);
    }

    $request_uri = $this->getRequest()->getRequestURI();
    $actions[] = id(new PhabricatorActionView())
      ->setIcon('fa-download')
      ->setName(pht('Download Raw Diff'))
      ->setHref($request_uri->alter('download', 'true'));

    return $actions;
  }

  private function getRevisionCommentActions(DifferentialRevision $revision) {
    $actions = array(
      DifferentialAction::ACTION_COMMENT => true,
    );

    $viewer = $this->getRequest()->getUser();
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
      ->setHeader(pht('Recent Similar Open Revisions'));

    $view = id(new DifferentialRevisionListView())
      ->setHeader($header)
      ->setRevisions($revisions)
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

    $viewer = $this->getRequest()->getUser();

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

    // Make sure we're only going to render unique diffs.
    $diffs = mpull($diffs, null, 'getID');
    $labels = array(pht('Left'), pht('Right'));

    $property_lists = array();
    foreach ($diffs as $diff) {
      if (count($diffs) == 2) {
        $label = array_shift($labels);
        $label = pht('%s (Diff %d)', $label, $diff->getID());
      } else {
        $label = pht('Diff %d', $diff->getID());
      }

      $property_lists[] = array(
        $label,
        $this->buildDiffPropertyList($diff, $revision, $fields),
      );
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Diff Detail'))
      ->setUser($viewer);

    $last_tab = null;
    foreach ($property_lists as $key => $property_list) {
      list($tab_name, $list_view) = $property_list;

      $tab = id(new PHUIListItemView())
        ->setKey($key)
        ->setName($tab_name);

      $box->addPropertyList($list_view, $tab);
      $last_tab = $tab;
    }

    if ($last_tab) {
      $last_tab->setSelected(true);
    }

    return $box;
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
      ->withOperationStates(
        array(
          DrydockRepositoryOperation::STATE_WAIT,
          DrydockRepositoryOperation::STATE_WORK,
          DrydockRepositoryOperation::STATE_FAIL,
        ))
      ->execute();
    if (!$operations) {
      return null;
    }

    $operation = head(msort($operations, 'getID'));

    $box_view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Active Operations'));

    return id(new DrydockRepositoryOperationStatusView())
      ->setUser($viewer)
      ->setBoxView($box_view)
      ->setOperation($operation);
  }

}
