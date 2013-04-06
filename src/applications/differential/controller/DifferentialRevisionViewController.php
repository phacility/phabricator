<?php

final class DifferentialRevisionViewController extends DifferentialController {

  private $revisionID;

  public function shouldRequireLogin() {
    return !$this->allowsAnonymousAccess();
  }

  public function willProcessRequest(array $data) {
    $this->revisionID = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $viewer_is_anonymous = !$user->isLoggedIn();

    $revision = id(new DifferentialRevision())->load($this->revisionID);
    if (!$revision) {
      return new Aphront404Response();
    }

    $revision->loadRelationships();

    $diffs = $revision->loadDiffs();

    if (!$diffs) {
      throw new Exception(
        "This revision has no diffs. Something has gone quite wrong.");
    }

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

    $arc_project = $target->loadArcanistProject();
    $repository = ($arc_project ? $arc_project->loadRepository() : null);

    list($changesets, $vs_map, $vs_changesets, $rendering_references) =
      $this->loadChangesetsAndVsMap(
        $target,
        idx($diffs, $diff_vs),
        $repository);

    if ($request->getExists('download')) {
      return $this->buildRawDiffResponse(
        $changesets,
        $vs_changesets,
        $vs_map,
        $repository);
    }

    $props = id(new DifferentialDiffProperty())->loadAllWhere(
      'diffID = %d',
      $target_manual->getID());
    $props = mpull($props, 'getData', 'getName');

    $aux_fields = $this->loadAuxiliaryFields($revision);

    $comments = $revision->loadComments();
    $comments = array_merge(
      $this->getImplicitComments($revision, reset($diffs)),
      $comments);

    $all_changesets = $changesets;
    $inlines = $this->loadInlineComments($comments, $all_changesets);

    $object_phids = array_merge(
      $revision->getReviewers(),
      $revision->getCCPHIDs(),
      $revision->loadCommitPHIDs(),
      array(
        $revision->getAuthorPHID(),
        $user->getPHID(),
      ),
      mpull($comments, 'getAuthorPHID'));

    foreach ($comments as $comment) {
      $metadata = $comment->getMetadata();
      $added_reviewers = idx(
        $metadata,
        DifferentialComment::METADATA_ADDED_REVIEWERS);
      if ($added_reviewers) {
        foreach ($added_reviewers as $phid) {
          $object_phids[] = $phid;
        }
      }
      $added_ccs = idx(
        $metadata,
        DifferentialComment::METADATA_ADDED_CCS);
      if ($added_ccs) {
        foreach ($added_ccs as $phid) {
          $object_phids[] = $phid;
        }
      }
    }

    foreach ($revision->getAttached() as $type => $phids) {
      foreach ($phids as $phid => $info) {
        $object_phids[] = $phid;
      }
    }

    $aux_phids = array();
    foreach ($aux_fields as $key => $aux_field) {
      $aux_field->setDiff($target);
      $aux_field->setManualDiff($target_manual);
      $aux_field->setDiffProperties($props);
      $aux_phids[$key] = $aux_field->getRequiredHandlePHIDsForRevisionView();
    }
    $object_phids = array_merge($object_phids, array_mergev($aux_phids));
    $object_phids = array_unique($object_phids);

    $handles = $this->loadViewerHandles($object_phids);

    foreach ($aux_fields as $key => $aux_field) {
      // Make sure each field only has access to handles it specifically
      // requested, not all handles. Otherwise you can get a field which works
      // only in the presence of other fields.
      $aux_field->setHandles(array_select_keys($handles, $aux_phids[$key]));
    }

    $reviewer_warning = null;
    if ($revision->getStatus() ==
        ArcanistDifferentialRevisionStatus::NEEDS_REVIEW) {
      $has_live_reviewer = false;
      foreach ($revision->getReviewers() as $reviewer) {
        if (!$handles[$reviewer]->isDisabled()) {
          $has_live_reviewer = true;
          break;
        }
      }
      if (!$has_live_reviewer) {
        $reviewer_warning = new AphrontErrorView();
        $reviewer_warning->setSeverity(AphrontErrorView::SEVERITY_WARNING);
        $reviewer_warning->setTitle(pht('No Active Reviewers'));
        if ($revision->getReviewers()) {
          $reviewer_warning->appendChild(
            phutil_tag(
              'p',
              array(),
              pht('All specified reviewers are disabled and this revision '.
                  'needs review. You may want to add some new reviewers.')));
        } else {
          $reviewer_warning->appendChild(
            phutil_tag(
              'p',
              array(),
              pht('This revision has no specified reviewers and needs '.
                  'review. You may want to add some reviewers.')));
        }
      }
    }

    $request_uri = $request->getRequestURI();

    $limit = 100;
    $large = $request->getStr('large');
    if (count($changesets) > $limit && !$large) {
      $count = count($changesets);
      $warning = new AphrontErrorView();
      $warning->setTitle('Very Large Diff');
      $warning->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $warning->appendChild(hsprintf(
        '%s <strong>%s</strong>',
        pht(
          'This diff is very large and affects %s files. Load each file '.
            'individually.',
          new PhutilNumber($count)),
        phutil_tag(
          'a',
          array(
            'href' => $request_uri
              ->alter('large', 'true')
              ->setFragment('toc'),
          ),
          pht('Show All Files Inline'))));
      $warning = $warning->render();

      $my_inlines = id(new DifferentialInlineComment())->loadAllWhere(
        'revisionID = %d AND commentID IS NULL AND authorPHID = %s AND '.
          'changesetID IN (%Ld)',
        $this->revisionID,
        $user->getPHID(),
        mpull($changesets, 'getID'));

      $visible_changesets = array();
      foreach ($inlines + $my_inlines as $inline) {
        $changeset_id = $inline->getChangesetID();
        if (isset($changesets[$changeset_id])) {
          $visible_changesets[$changeset_id] = $changesets[$changeset_id];
        }
      }

      if (!empty($props['arc:lint'])) {
        $changeset_paths = mpull($changesets, null, 'getFilename');
        foreach ($props['arc:lint'] as $lint) {
          $changeset = idx($changeset_paths, $lint['path']);
          if ($changeset) {
            $visible_changesets[$changeset->getID()] = $changeset;
          }
        }
      }

    } else {
      $warning = null;
      $visible_changesets = $changesets;
    }

    $revision_detail = new DifferentialRevisionDetailView();
    $revision_detail->setRevision($revision);
    $revision_detail->setDiff(end($diffs));
    $revision_detail->setAuxiliaryFields($aux_fields);

    $actions = $this->getRevisionActions($revision);

    $custom_renderer_class = PhabricatorEnv::getEnvConfig(
      'differential.revision-custom-detail-renderer');
    if ($custom_renderer_class) {

      // TODO: build a better version of the action links and deprecate the
      // whole DifferentialRevisionDetailRenderer class.
      $custom_renderer = newv($custom_renderer_class, array());
      $custom_renderer->setUser($user);
      $custom_renderer->setDiff($target);
      if ($diff_vs) {
        $custom_renderer->setVSDiff($diffs[$diff_vs]);
      }
      $actions = array_merge(
        $actions,
        $custom_renderer->generateActionLinks($revision, $target_manual));
    }

    $whitespace = $request->getStr(
      'whitespace',
      DifferentialChangesetParser::WHITESPACE_IGNORE_ALL);

    if ($arc_project) {
      list($symbol_indexes, $project_phids) = $this->buildSymbolIndexes(
        $arc_project,
        $visible_changesets);
    } else {
      $symbol_indexes = array();
      $project_phids = null;
    }

    $revision_detail->setActions($actions);
    $revision_detail->setUser($user);

    $comment_view = new DifferentialRevisionCommentListView();
    $comment_view->setComments($comments);
    $comment_view->setHandles($handles);
    $comment_view->setInlineComments($inlines);
    $comment_view->setChangesets($all_changesets);
    $comment_view->setUser($user);
    $comment_view->setTargetDiff($target);
    $comment_view->setVersusDiffID($diff_vs);

    if ($arc_project) {
      Javelin::initBehavior(
        'repository-crossreference',
        array(
          'section' => $comment_view->getID(),
          'projects' => $project_phids,
        ));
    }

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

    $changeset_view->setUser($user);
    $changeset_view->setDiff($target);
    $changeset_view->setRenderingReferences($rendering_references);
    $changeset_view->setVsMap($vs_map);
    $changeset_view->setWhitespace($whitespace);
    if ($repository) {
      $changeset_view->setRepository($repository);
    }
    $changeset_view->setSymbolIndexes($symbol_indexes);
    $changeset_view->setTitle('Diff '.$target->getID());

    $diff_history = new DifferentialRevisionUpdateHistoryView();
    $diff_history->setDiffs($diffs);
    $diff_history->setSelectedVersusDiffID($diff_vs);
    $diff_history->setSelectedDiffID($target->getID());
    $diff_history->setSelectedWhitespace($whitespace);
    $diff_history->setUser($user);

    $local_view = new DifferentialLocalCommitsView();
    $local_view->setUser($user);
    $local_view->setLocalCommits(idx($props, 'local:commits'));

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

    $toc_view = new DifferentialDiffTableOfContentsView();
    $toc_view->setChangesets($changesets);
    $toc_view->setVisibleChangesets($visible_changesets);
    $toc_view->setRenderingReferences($rendering_references);
    $toc_view->setUnitTestData(idx($props, 'arc:unit', array()));
    if ($repository) {
      $toc_view->setRepository($repository);
    }
    $toc_view->setDiff($target);
    $toc_view->setUser($user);
    $toc_view->setRevisionID($revision->getID());
    $toc_view->setWhitespace($whitespace);

    $comment_form = null;
    if (!$viewer_is_anonymous) {
      $draft = id(new PhabricatorDraft())->loadOneWhere(
        'authorPHID = %s AND draftKey = %s',
        $user->getPHID(),
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
      $comment_form->setAuxFields($aux_fields);
      $comment_form->setActions($this->getRevisionCommentActions($revision));
      $comment_form->setActionURI('/differential/comment/save/');
      $comment_form->setUser($user);
      $comment_form->setDraft($draft);
      $comment_form->setReviewers(mpull($reviewers, 'getFullName', 'getPHID'));
      $comment_form->setCCs(mpull($ccs, 'getFullName', 'getPHID'));
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
      ->appendChild(array(
        $comment_view->render(),
        $diff_history->render(),
        $warning,
        $local_view->render(),
        $toc_view->render(),
        $other_view,
        $changeset_view->render(),
      ));
    if ($comment_form) {
      $page_pane->appendChild($comment_form->render());
    }

    PhabricatorFeedStoryNotification::updateObjectNotificationViews(
      $user, $revision->getPHID());

    $object_id = 'D'.$revision->getID();

    $top_anchor = id(new PhabricatorAnchorView())
      ->setAnchorName('top')
      ->setNavigationMarker(true);

    $content = array(
      $reviewer_warning,
      $top_anchor,
      $revision_detail,
      $page_pane,
    );

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($object_id)
        ->setHref('/'.$object_id));

    $prefs = $user->loadPreferences();

    $pref_filetree = PhabricatorUserPreferences::PREFERENCE_DIFF_FILETREE;
    if ($prefs->getPreference($pref_filetree)) {
      $collapsed = $prefs->getPreference(
        PhabricatorUserPreferences::PREFERENCE_NAV_COLLAPSED,
        false);

      $nav = id(new DifferentialChangesetFileTreeSideNavBuilder())
        ->setAnchorName('top')
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
      ));
  }

  private function getImplicitComments(
    DifferentialRevision $revision,
    DifferentialDiff $diff) {

    $author_phid = nonempty(
      $diff->getAuthorPHID(),
      $revision->getAuthorPHID());

    $template = new DifferentialComment();
    $template->setAuthorPHID($author_phid);
    $template->setRevisionID($revision->getID());
    $template->setDateCreated($revision->getDateCreated());

    $comments = array();

    if (strlen($revision->getSummary())) {
      $summary_comment = clone $template;
      $summary_comment->setContent($revision->getSummary());
      $summary_comment->setAction(DifferentialAction::ACTION_SUMMARIZE);
      $comments[] = $summary_comment;
    }

    if (strlen($revision->getTestPlan())) {
      $testplan_comment = clone $template;
      $testplan_comment->setContent($revision->getTestPlan());
      $testplan_comment->setAction(DifferentialAction::ACTION_TESTPLAN);
      $comments[] = $testplan_comment;
    }

    return $comments;
  }

  private function getRevisionActions(DifferentialRevision $revision) {
    $user = $this->getRequest()->getUser();
    $viewer_phid = $user->getPHID();
    $viewer_is_owner = ($revision->getAuthorPHID() == $viewer_phid);
    $viewer_is_reviewer = in_array($viewer_phid, $revision->getReviewers());
    $viewer_is_cc = in_array($viewer_phid, $revision->getCCPHIDs());
    $viewer_is_anonymous = !$this->getRequest()->getUser()->isLoggedIn();
    $status = $revision->getStatus();
    $revision_id = $revision->getID();
    $revision_phid = $revision->getPHID();

    $links = array();

    if ($viewer_is_owner) {
      $links[] = array(
        'icon'  =>  'edit',
        'href'  => "/differential/revision/edit/{$revision_id}/",
        'name'  => pht('Edit Revision'),
      );
    }

    if (!$viewer_is_anonymous) {

      if (!$viewer_is_owner && !$viewer_is_reviewer) {
        $action = $viewer_is_cc ? 'rem' : 'add';
        $links[] = array(
          'icon'    => $viewer_is_cc ? 'subscribe-delete' : 'subscribe-add',
          'href'    => "/differential/subscribe/{$action}/{$revision_id}/",
          'name'    => $viewer_is_cc ? pht('Unsubscribe') : pht('Subscribe'),
          'instant' => true,
        );
      } else {
        $links[] = array(
          'icon'     => 'subscribe-auto',
          'name'     => pht('Automatically Subscribed'),
          'disabled' => true,
        );
      }

      require_celerity_resource('phabricator-object-selector-css');
      require_celerity_resource('javelin-behavior-phabricator-object-selector');

      $links[] = array(
        'icon'  => 'link',
        'name'  => pht('Edit Dependencies'),
        'href'  => "/search/attach/{$revision_phid}/DREV/dependencies/",
        'sigil' => 'workflow',
      );

      $maniphest = 'PhabricatorApplicationManiphest';
      if (PhabricatorApplication::isClassInstalled($maniphest)) {
        $links[] = array(
          'icon'  => 'attach',
          'name'  => pht('Edit Maniphest Tasks'),
          'href'  => "/search/attach/{$revision_phid}/TASK/",
          'sigil' => 'workflow',
        );
      }
    }

    $request_uri = $this->getRequest()->getRequestURI();
    $links[] = array(
      'icon'  => 'download',
      'name'  => pht('Download Raw Diff'),
      'href'  => $request_uri->alter('download', 'true')
    );

    return $links;
  }

  private function getRevisionCommentActions(DifferentialRevision $revision) {

    $actions = array(
      DifferentialAction::ACTION_COMMENT => true,
    );

    $viewer = $this->getRequest()->getUser();
    $viewer_phid = $viewer->getPHID();
    $viewer_is_owner = ($viewer_phid == $revision->getAuthorPHID());
    $viewer_is_reviewer = in_array($viewer_phid, $revision->getReviewers());
    $viewer_did_accept = ($viewer_phid === $revision->loadReviewedBy());
    $status = $revision->getStatus();

    $allow_self_accept = PhabricatorEnv::getEnvConfig(
      'differential.allow-self-accept');
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
          $actions[DifferentialAction::ACTION_ACCEPT] = true;
          $actions[DifferentialAction::ACTION_REJECT] = true;
          $actions[DifferentialAction::ACTION_RESIGN] = $viewer_is_reviewer;
          break;
        case ArcanistDifferentialRevisionStatus::NEEDS_REVISION:
          $actions[DifferentialAction::ACTION_ACCEPT] = true;
          $actions[DifferentialAction::ACTION_RESIGN] = $viewer_is_reviewer;
          break;
        case ArcanistDifferentialRevisionStatus::ACCEPTED:
          $actions[DifferentialAction::ACTION_REJECT] = true;
          $actions[DifferentialAction::ACTION_RESIGN] =
            $viewer_is_reviewer && !$viewer_did_accept;
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

  private function loadInlineComments(array $comments, array &$changesets) {
    assert_instances_of($comments, 'DifferentialComment');
    assert_instances_of($changesets, 'DifferentialChangeset');

    $inline_comments = array();

    $comment_ids = array_filter(mpull($comments, 'getID'));
    if (!$comment_ids) {
      return $inline_comments;
    }

    $inline_comments = id(new DifferentialInlineComment())
      ->loadAllWhere(
        'commentID in (%Ld)',
        $comment_ids);

    $load_changesets = array();
    foreach ($inline_comments as $inline) {
      $changeset_id = $inline->getChangesetID();
      if (isset($changesets[$changeset_id])) {
        continue;
      }
      $load_changesets[$changeset_id] = true;
    }

    $more_changesets = array();
    if ($load_changesets) {
      $changeset_ids = array_keys($load_changesets);
      $more_changesets += id(new DifferentialChangeset())
        ->loadAllWhere(
          'id IN (%Ld)',
          $changeset_ids);
    }

    if ($more_changesets) {
      $changesets += $more_changesets;
      $changesets = msort($changesets, 'getSortKey');
    }

    return $inline_comments;
  }

  private function loadChangesetsAndVsMap(
    DifferentialDiff $target,
    DifferentialDiff $diff_vs = null,
    PhabricatorRepository $repository = null) {

    $load_ids = array();
    if ($diff_vs) {
      $load_ids[] = $diff_vs->getID();
    }
    $load_ids[] = $target->getID();

    $raw_changesets = id(new DifferentialChangeset())
      ->loadAllWhere(
        'diffID IN (%Ld)',
        $load_ids);
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

  private function loadAuxiliaryFields(DifferentialRevision $revision) {

    $aux_fields = DifferentialFieldSelector::newSelector()
      ->getFieldSpecifications();
    foreach ($aux_fields as $key => $aux_field) {
      if (!$aux_field->shouldAppearOnRevisionView()) {
        unset($aux_fields[$key]);
      } else {
        $aux_field->setUser($this->getRequest()->getUser());
      }
    }

    $aux_fields = DifferentialAuxiliaryField::loadFromStorage(
      $revision,
      $aux_fields);

    return $aux_fields;
  }

  private function buildSymbolIndexes(
    PhabricatorRepositoryArcanistProject $arc_project,
    array $visible_changesets) {
    assert_instances_of($visible_changesets, 'DifferentialChangeset');

    $engine = PhabricatorSyntaxHighlighter::newEngine();

    $langs = $arc_project->getSymbolIndexLanguages();
    if (!$langs) {
      return array(array(), array());
    }

    $symbol_indexes = array();

    $project_phids = array_merge(
      array($arc_project->getPHID()),
      nonempty($arc_project->getSymbolIndexProjects(), array()));

    $indexed_langs = array_fill_keys($langs, true);
    foreach ($visible_changesets as $key => $changeset) {
      $lang = $engine->getLanguageFromFilename($changeset->getFilename());
      if (isset($indexed_langs[$lang])) {
        $symbol_indexes[$key] = array(
          'lang'      => $lang,
          'projects'  => $project_phids,
        );
      }
    }

    return array($symbol_indexes, $project_phids);
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

    $query = id(new DifferentialRevisionQuery())
      ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
      ->setOrder(DifferentialRevisionQuery::ORDER_PATH_MODIFIED)
      ->setLimit(10)
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

    $user = $this->getRequest()->getUser();

    $view = id(new DifferentialRevisionListView())
      ->setRevisions($revisions)
      ->setFields(DifferentialRevisionListView::getDefaultFields($user))
      ->setUser($user)
      ->loadAssets();

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    return hsprintf(
      '%s<div class="differential-panel">%s</div>',
      id(new PhabricatorHeaderView())
        ->setHeader(pht('Open Revisions Affecting These Files'))
        ->render(),
      $view->render());
  }

  /**
   * Straight copy of the loadFileByPhid method in
   * @{class:DifferentialReviewRequestMail}.
   *
   * This is because of the code similarity between the buildPatch method in
   * @{class:DifferentialReviewRequestMail} and @{method:buildRawDiffResponse}
   * in this class. Both of these methods end up using call_user_func and this
   * piece of code is the lucky function.
   *
   * @return mixed (@{class:PhabricatorFile} if found, null if not)
   */
  public function loadFileByPHID($phid) {
    $file = id(new PhabricatorFile())->loadOneWhere(
      'phid = %s',
      $phid);
    if (!$file) {
      return null;
    }
    return $file->loadFileData();
  }

  /**
   * Note this code is somewhat similar to the buildPatch method in
   * @{class:DifferentialReviewRequestMail}.
   *
   * @return @{class:AphrontRedirectResponse}
   */
  private function buildRawDiffResponse(
    array $changesets,
    array $vs_changesets,
    array $vs_map,
    PhabricatorRepository $repository = null) {

    assert_instances_of($changesets,    'DifferentialChangeset');
    assert_instances_of($vs_changesets, 'DifferentialChangeset');

    $engine = new PhabricatorDifferenceEngine();
    $generated_changesets = array();
    foreach ($changesets as $changeset) {
      $changeset->attachHunks($changeset->loadHunks());
      $right = $changeset->makeNewFile();
      $choice = $changeset;
      $vs = idx($vs_map, $changeset->getID());
      if ($vs == -1) {
        $left = $right;
        $right = $changeset->makeOldFile();
      } else if ($vs) {
        $choice = $vs_changeset = $vs_changesets[$vs];
        $vs_changeset->attachHunks($vs_changeset->loadHunks());
        $left = $vs_changeset->makeNewFile();
      } else {
        $left = $changeset->makeOldFile();
      }

      $synthetic = $engine->generateChangesetFromFileContent(
        $left,
        $right);

      if (!$synthetic->getAffectedLineCount()) {
        $filetype = $choice->getFileType();
        if ($filetype == DifferentialChangeType::FILE_TEXT ||
            $filetype == DifferentialChangeType::FILE_SYMLINK) {
          continue;
        }
      }

      $choice->attachHunks($synthetic->getHunks());

      $generated_changesets[] = $choice;
    }

    $diff = new DifferentialDiff();
    $diff->attachChangesets($generated_changesets);
    $diff_dict = $diff->getDiffDict();

    $changes = array();
    foreach ($diff_dict['changes'] as $changedict) {
      $changes[] = ArcanistDiffChange::newFromDictionary($changedict);
    }
    $bundle = ArcanistBundle::newFromChanges($changes);

    $bundle->setLoadFileDataCallback(array($this, 'loadFileByPHID'));

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
      ));

    return id(new AphrontRedirectResponse())->setURI($file->getBestURI());

  }
}
