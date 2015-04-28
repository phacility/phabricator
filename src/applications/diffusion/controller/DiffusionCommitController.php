<?php

final class DiffusionCommitController extends DiffusionController {

  const CHANGES_LIMIT = 100;

  private $auditAuthorityPHIDs;
  private $highlightedAudits;

  public function shouldAllowPublic() {
    return true;
  }

  protected function shouldLoadDiffusionRequest() {
    return false;
  }

  protected function processDiffusionRequest(AphrontRequest $request) {
    $user = $request->getUser();
    // This controller doesn't use blob/path stuff, just pass the dictionary
    // in directly instead of using the AphrontRequest parsing mechanism.
    $data = $request->getURIMap();
    $data['user'] = $user;
    $drequest = DiffusionRequest::newFromDictionary($data);
    $this->diffusionRequest = $drequest;

    if ($request->getStr('diff')) {
      return $this->buildRawDiffResponse($drequest);
    }

    $repository = $drequest->getRepository();
    $callsign = $repository->getCallsign();

    $content = array();
    $commit = id(new DiffusionCommitQuery())
      ->setViewer($request->getUser())
      ->withRepository($repository)
      ->withIdentifiers(array($drequest->getCommit()))
      ->needCommitData(true)
      ->needAuditRequests(true)
      ->executeOne();

    $crumbs = $this->buildCrumbs(array(
      'commit' => true,
    ));

    if (!$commit) {
      $exists = $this->callConduitWithDiffusionRequest(
        'diffusion.existsquery',
        array('commit' => $drequest->getCommit()));
      if (!$exists) {
        return new Aphront404Response();
      }

      $error = id(new PHUIInfoView())
        ->setTitle(pht('Commit Still Parsing'))
        ->appendChild(
          pht(
            'Failed to load the commit because the commit has not been '.
            'parsed yet.'));

      return $this->buildApplicationPage(
        array(
          $crumbs,
          $error,
        ),
        array(
          'title' => pht('Commit Still Parsing'),
        ));
    }

    $audit_requests = $commit->getAudits();
    $this->auditAuthorityPHIDs =
      PhabricatorAuditCommentEditor::loadAuditPHIDsForUser($user);

    $commit_data = $commit->getCommitData();
    $is_foreign = $commit_data->getCommitDetail('foreign-svn-stub');
    $changesets = null;
    if ($is_foreign) {
      $subpath = $commit_data->getCommitDetail('svn-subpath');

      $error_panel = new PHUIInfoView();
      $error_panel->setTitle(pht('Commit Not Tracked'));
      $error_panel->setSeverity(PHUIInfoView::SEVERITY_WARNING);
      $error_panel->appendChild(
        pht("This Diffusion repository is configured to track only one ".
        "subdirectory of the entire Subversion repository, and this commit ".
        "didn't affect the tracked subdirectory ('%s'), so no ".
        "information is available.", $subpath));
      $content[] = $error_panel;
    } else {
      $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();
      $engine->setConfig('viewer', $user);

      require_celerity_resource('phabricator-remarkup-css');

      $parents = $this->callConduitWithDiffusionRequest(
        'diffusion.commitparentsquery',
        array('commit' => $drequest->getCommit()));

      if ($parents) {
        $parents = id(new DiffusionCommitQuery())
          ->setViewer($user)
          ->withRepository($repository)
          ->withIdentifiers($parents)
          ->execute();
      }

      $headsup_view = id(new PHUIHeaderView())
        ->setHeader(nonempty($commit->getSummary(), pht('Commit Detail')));

      $headsup_actions = $this->renderHeadsupActionList($commit, $repository);

      $commit_properties = $this->loadCommitProperties(
        $commit,
        $commit_data,
        $parents,
        $audit_requests);
      $property_list = id(new PHUIPropertyListView())
        ->setHasKeyboardShortcuts(true)
        ->setUser($user)
        ->setObject($commit);
      foreach ($commit_properties as $key => $value) {
        $property_list->addProperty($key, $value);
      }

      $message = $commit_data->getCommitMessage();

      $revision = $commit->getCommitIdentifier();
      $message = $this->linkBugtraq($message);

      $message = $engine->markupText($message);

      $property_list->invokeWillRenderEvent();
      $property_list->setActionList($headsup_actions);

      $detail_list = new PHUIPropertyListView();
      $detail_list->addSectionHeader(
        pht('Description'),
        PHUIPropertyListView::ICON_SUMMARY);
      $detail_list->addTextContent(
        phutil_tag(
          'div',
          array(
            'class' => 'diffusion-commit-message phabricator-remarkup',
          ),
          $message));

      $object_box = id(new PHUIObjectBoxView())
        ->setHeader($headsup_view)
        ->addPropertyList($property_list)
        ->addPropertyList($detail_list);

      $content[] = $object_box;
    }

    $content[] = $this->buildComments($commit);

    $hard_limit = 1000;

    if ($commit->isImported()) {
      $change_query = DiffusionPathChangeQuery::newFromDiffusionRequest(
        $drequest);
      $change_query->setLimit($hard_limit + 1);
      $changes = $change_query->loadChanges();
    } else {
      $changes = array();
    }

    $was_limited = (count($changes) > $hard_limit);
    if ($was_limited) {
      $changes = array_slice($changes, 0, $hard_limit);
    }

    $content[] = $this->buildMergesTable($commit);

    $highlighted_audits = $commit->getAuthorityAudits(
      $user,
      $this->auditAuthorityPHIDs);

    $owners_paths = array();
    if ($highlighted_audits) {
      $packages = id(new PhabricatorOwnersPackage())->loadAllWhere(
        'phid IN (%Ls)',
        mpull($highlighted_audits, 'getAuditorPHID'));
      if ($packages) {
        $owners_paths = id(new PhabricatorOwnersPath())->loadAllWhere(
          'repositoryPHID = %s AND packageID IN (%Ld)',
          $repository->getPHID(),
          mpull($packages, 'getID'));
      }
    }

    $change_table = new DiffusionCommitChangeTableView();
    $change_table->setDiffusionRequest($drequest);
    $change_table->setPathChanges($changes);
    $change_table->setOwnersPaths($owners_paths);

    $count = count($changes);

    $bad_commit = null;
    if ($count == 0) {
      $bad_commit = queryfx_one(
        id(new PhabricatorRepository())->establishConnection('r'),
        'SELECT * FROM %T WHERE fullCommitName = %s',
        PhabricatorRepository::TABLE_BADCOMMIT,
        'r'.$callsign.$commit->getCommitIdentifier());
    }

    if ($bad_commit) {
      $content[] = $this->renderStatusMessage(
        pht('Bad Commit'),
        $bad_commit['description']);
    } else if ($is_foreign) {
      // Don't render anything else.
    } else if (!$commit->isImported()) {
      $content[] = $this->renderStatusMessage(
        pht('Still Importing...'),
        pht(
          'This commit is still importing. Changes will be visible once '.
          'the import finishes.'));
    } else if (!count($changes)) {
      $content[] = $this->renderStatusMessage(
        pht('Empty Commit'),
        pht(
          'This commit is empty and does not affect any paths.'));
    } else if ($was_limited) {
      $content[] = $this->renderStatusMessage(
        pht('Enormous Commit'),
        pht(
          'This commit is enormous, and affects more than %d files. '.
          'Changes are not shown.',
          $hard_limit));
    } else {
      // The user has clicked "Show All Changes", and we should show all the
      // changes inline even if there are more than the soft limit.
      $show_all_details = $request->getBool('show_all');

      $change_panel = new PHUIObjectBoxView();
      $header = new PHUIHeaderView();
      $header->setHeader('Changes ('.number_format($count).')');
      $change_panel->setID('toc');
      if ($count > self::CHANGES_LIMIT && !$show_all_details) {

        $icon = id(new PHUIIconView())
          ->setIconFont('fa-files-o');

        $button = id(new PHUIButtonView())
          ->setText(pht('Show All Changes'))
          ->setHref('?show_all=true')
          ->setTag('a')
          ->setIcon($icon);

        $warning_view = id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
          ->setTitle('Very Large Commit')
          ->appendChild(
            pht('This commit is very large. Load each file individually.'));

        $change_panel->setInfoView($warning_view);
        $header->addActionLink($button);
      }

      $change_panel->appendChild($change_table);
      $change_panel->setHeader($header);

      $content[] = $change_panel;

      $changesets = DiffusionPathChange::convertToDifferentialChangesets(
        $user,
        $changes);

      $vcs = $repository->getVersionControlSystem();
      switch ($vcs) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $vcs_supports_directory_changes = true;
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $vcs_supports_directory_changes = false;
          break;
        default:
          throw new Exception('Unknown VCS.');
      }

      $references = array();
      foreach ($changesets as $key => $changeset) {
        $file_type = $changeset->getFileType();
        if ($file_type == DifferentialChangeType::FILE_DIRECTORY) {
          if (!$vcs_supports_directory_changes) {
            unset($changesets[$key]);
            continue;
          }
        }

        $references[$key] = $drequest->generateURI(
          array(
            'action' => 'rendering-ref',
            'path'   => $changeset->getFilename(),
          ));
      }

      // TODO: Some parts of the views still rely on properties of the
      // DifferentialChangeset. Make the objects ephemeral to make sure we don't
      // accidentally save them, and then set their ID to the appropriate ID for
      // this application (the path IDs).
      $path_ids = array_flip(mpull($changes, 'getPath'));
      foreach ($changesets as $changeset) {
        $changeset->makeEphemeral();
        $changeset->setID($path_ids[$changeset->getFilename()]);
      }

      if ($count <= self::CHANGES_LIMIT || $show_all_details) {
        $visible_changesets = $changesets;
      } else {
        $visible_changesets = array();
        $inlines = PhabricatorAuditInlineComment::loadDraftAndPublishedComments(
          $user,
          $commit->getPHID());
        $path_ids = mpull($inlines, null, 'getPathID');
        foreach ($changesets as $key => $changeset) {
          if (array_key_exists($changeset->getID(), $path_ids)) {
            $visible_changesets[$key] = $changeset;
          }
        }
      }

      $change_list_title = DiffusionView::nameCommit(
        $repository,
        $commit->getCommitIdentifier());
      $change_list = new DifferentialChangesetListView();
      $change_list->setTitle($change_list_title);
      $change_list->setChangesets($changesets);
      $change_list->setVisibleChangesets($visible_changesets);
      $change_list->setRenderingReferences($references);
      $change_list->setRenderURI('/diffusion/'.$callsign.'/diff/');
      $change_list->setRepository($repository);
      $change_list->setUser($user);

      // TODO: Try to setBranch() to something reasonable here?

      $change_list->setStandaloneURI(
        '/diffusion/'.$callsign.'/diff/');
      $change_list->setRawFileURIs(
        // TODO: Implement this, somewhat tricky if there's an octopus merge
        // or whatever?
        null,
        '/diffusion/'.$callsign.'/diff/?view=r');

      $change_list->setInlineCommentControllerURI(
        '/diffusion/inline/edit/'.phutil_escape_uri($commit->getPHID()).'/');

      $change_references = array();
      foreach ($changesets as $key => $changeset) {
        $change_references[$changeset->getID()] = $references[$key];
      }
      $change_table->setRenderingReferences($change_references);

      $content[] = $change_list->render();
    }

    $content[] = $this->renderAddCommentPanel($commit, $audit_requests);

    $commit_id = 'r'.$callsign.$commit->getCommitIdentifier();
    $short_name = DiffusionView::nameCommit(
      $repository,
      $commit->getCommitIdentifier());

    $prefs = $user->loadPreferences();
    $pref_filetree = PhabricatorUserPreferences::PREFERENCE_DIFF_FILETREE;
    $pref_collapse = PhabricatorUserPreferences::PREFERENCE_NAV_COLLAPSED;
    $show_filetree = $prefs->getPreference($pref_filetree);
    $collapsed = $prefs->getPreference($pref_collapse);

    if ($changesets && $show_filetree) {
      $nav = id(new DifferentialChangesetFileTreeSideNavBuilder())
        ->setTitle($short_name)
        ->setBaseURI(new PhutilURI('/'.$commit_id))
        ->build($changesets)
        ->setCrumbs($crumbs)
        ->setCollapsed((bool)$collapsed)
        ->appendChild($content);
      $content = $nav;
    } else {
      $content = array($crumbs, $content);
    }

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => $commit_id,
        'pageObjects' => array($commit->getPHID()),
      ));
  }

  private function loadCommitProperties(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data,
    array $parents,
    array $audit_requests) {

    assert_instances_of($parents, 'PhabricatorRepositoryCommit');
    $viewer = $this->getRequest()->getUser();
    $commit_phid = $commit->getPHID();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $edge_query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($commit_phid))
      ->withEdgeTypes(array(
        DiffusionCommitHasTaskEdgeType::EDGECONST,
        DiffusionCommitHasRevisionEdgeType::EDGECONST,
        DiffusionCommitRevertsCommitEdgeType::EDGECONST,
        DiffusionCommitRevertedByCommitEdgeType::EDGECONST,
      ));

    $edges = $edge_query->execute();

    $task_phids = array_keys(
      $edges[$commit_phid][DiffusionCommitHasTaskEdgeType::EDGECONST]);
    $revision_phid = key(
      $edges[$commit_phid][DiffusionCommitHasRevisionEdgeType::EDGECONST]);

    $reverts_phids = array_keys(
      $edges[$commit_phid][DiffusionCommitRevertsCommitEdgeType::EDGECONST]);
    $reverted_by_phids = array_keys(
      $edges[$commit_phid][DiffusionCommitRevertedByCommitEdgeType::EDGECONST]);

    $phids = $edge_query->getDestinationPHIDs(array($commit_phid));

    if ($data->getCommitDetail('authorPHID')) {
      $phids[] = $data->getCommitDetail('authorPHID');
    }
    if ($data->getCommitDetail('reviewerPHID')) {
      $phids[] = $data->getCommitDetail('reviewerPHID');
    }
    if ($data->getCommitDetail('committerPHID')) {
      $phids[] = $data->getCommitDetail('committerPHID');
    }
    if ($parents) {
      foreach ($parents as $parent) {
        $phids[] = $parent->getPHID();
      }
    }

    // NOTE: We should never normally have more than a single push log, but
    // it can occur naturally if a commit is pushed, then the branch it was
    // on is deleted, then the commit is pushed again (or through other similar
    // chains of events). This should be rare, but does not indicate a bug
    // or data issue.

    // NOTE: We never query push logs in SVN because the commiter is always
    // the pusher and the commit time is always the push time; the push log
    // is redundant and we save a query by skipping it.

    $push_logs = array();
    if ($repository->isHosted() && !$repository->isSVN()) {
      $push_logs = id(new PhabricatorRepositoryPushLogQuery())
        ->setViewer($viewer)
        ->withRepositoryPHIDs(array($repository->getPHID()))
        ->withNewRefs(array($commit->getCommitIdentifier()))
        ->withRefTypes(array(PhabricatorRepositoryPushLog::REFTYPE_COMMIT))
        ->execute();
      foreach ($push_logs as $log) {
        $phids[] = $log->getPusherPHID();
      }
    }

    $handles = array();
    if ($phids) {
      $handles = $this->loadViewerHandles($phids);
    }

    $props = array();

    if ($commit->getAuditStatus()) {
      $status = PhabricatorAuditCommitStatusConstants::getStatusName(
        $commit->getAuditStatus());
      $tag = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_STATE)
        ->setName($status);

      switch ($commit->getAuditStatus()) {
        case PhabricatorAuditCommitStatusConstants::NEEDS_AUDIT:
          $tag->setBackgroundColor(PHUITagView::COLOR_ORANGE);
          break;
        case PhabricatorAuditCommitStatusConstants::CONCERN_RAISED:
          $tag->setBackgroundColor(PHUITagView::COLOR_RED);
          break;
        case PhabricatorAuditCommitStatusConstants::PARTIALLY_AUDITED:
          $tag->setBackgroundColor(PHUITagView::COLOR_BLUE);
          break;
        case PhabricatorAuditCommitStatusConstants::FULLY_AUDITED:
          $tag->setBackgroundColor(PHUITagView::COLOR_GREEN);
          break;
      }

      $props['Status'] = $tag;
    }

    if ($audit_requests) {
      $user_requests = array();
      $other_requests = array();
      foreach ($audit_requests as $audit_request) {
        if ($audit_request->isUser()) {
          $user_requests[] = $audit_request;
        } else {
          $other_requests[] = $audit_request;
        }
      }

      if ($user_requests) {
        $props['Auditors'] = $this->renderAuditStatusView(
          $user_requests);
      }

      if ($other_requests) {
        $props['Project/Package Auditors'] = $this->renderAuditStatusView(
          $other_requests);
      }
    }

    $author_phid = $data->getCommitDetail('authorPHID');
    $author_name = $data->getAuthorName();

    if (!$repository->isSVN()) {
      $authored_info = id(new PHUIStatusItemView());
      // TODO: In Git, a distinct authorship date is available. When present,
      // we should show it here.

      if ($author_phid) {
        $authored_info->setTarget($handles[$author_phid]->renderLink());
      } else if (strlen($author_name)) {
        $authored_info->setTarget($author_name);
      }

      $props['Authored'] = id(new PHUIStatusListView())
        ->addItem($authored_info);
    }

    $committed_info = id(new PHUIStatusItemView())
      ->setNote(phabricator_datetime($commit->getEpoch(), $viewer));

    $committer_phid = $data->getCommitDetail('committerPHID');
    $committer_name = $data->getCommitDetail('committer');
    if ($committer_phid) {
      $committed_info->setTarget($handles[$committer_phid]->renderLink());
    } else if (strlen($committer_name)) {
      $committed_info->setTarget($committer_name);
    } else if ($author_phid) {
      $committed_info->setTarget($handles[$author_phid]->renderLink());
    } else if (strlen($author_name)) {
      $committed_info->setTarget($author_name);
    }

    $props['Committed'] = id(new PHUIStatusListView())
      ->addItem($committed_info);

    if ($push_logs) {
      $pushed_list = new PHUIStatusListView();

      foreach ($push_logs as $push_log) {
        $pushed_item = id(new PHUIStatusItemView())
          ->setTarget($handles[$push_log->getPusherPHID()]->renderLink())
          ->setNote(phabricator_datetime($push_log->getEpoch(), $viewer));
        $pushed_list->addItem($pushed_item);
      }

      $props['Pushed'] = $pushed_list;
    }

    $reviewer_phid = $data->getCommitDetail('reviewerPHID');
    if ($reviewer_phid) {
      $props['Reviewer'] = $handles[$reviewer_phid]->renderLink();
    }

    if ($revision_phid) {
      $props['Differential Revision'] = $handles[$revision_phid]->renderLink();
    }

    if ($parents) {
      $parent_links = array();
      foreach ($parents as $parent) {
        $parent_links[] = $handles[$parent->getPHID()]->renderLink();
      }
      $props['Parents'] = phutil_implode_html(" \xC2\xB7 ", $parent_links);
    }

    $props['Branches'] = phutil_tag(
      'span',
      array(
        'id' => 'commit-branches',
      ),
      pht('Unknown'));
    $props['Tags'] = phutil_tag(
      'span',
      array(
        'id' => 'commit-tags',
      ),
      pht('Unknown'));

    $callsign = $repository->getCallsign();
    $root = '/diffusion/'.$callsign.'/commit/'.$commit->getCommitIdentifier();
    Javelin::initBehavior(
      'diffusion-commit-branches',
      array(
        $root.'/branches/' => 'commit-branches',
        $root.'/tags/' => 'commit-tags',
      ));

    $refs = $this->buildRefs($drequest);
    if ($refs) {
      $props['References'] = $refs;
    }

    if ($reverts_phids) {
      $props[pht('Reverts')] = $viewer->renderHandleList($reverts_phids);
    }

    if ($reverted_by_phids) {
      $props[pht('Reverted By')] = $viewer->renderHandleList(
        $reverted_by_phids);
    }

    if ($task_phids) {
      $task_list = array();
      foreach ($task_phids as $phid) {
        $task_list[] = $handles[$phid]->renderLink();
      }
      $task_list = phutil_implode_html(phutil_tag('br'), $task_list);
      $props['Tasks'] = $task_list;
    }

    return $props;
  }

  private function buildComments(PhabricatorRepositoryCommit $commit) {
    $timeline = $this->buildTransactionTimeline(
      $commit,
      new PhabricatorAuditTransactionQuery());
    $commit->willRenderTimeline($timeline, $this->getRequest());
    return $timeline;
  }

  private function renderAddCommentPanel(
    PhabricatorRepositoryCommit $commit,
    array $audit_requests) {
    assert_instances_of($audit_requests, 'PhabricatorRepositoryAuditRequest');

    $request = $this->getRequest();
    $user = $request->getUser();

    if (!$user->isLoggedIn()) {
      return id(new PhabricatorApplicationTransactionCommentView())
        ->setUser($user)
        ->setRequestURI($request->getRequestURI());
    }

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $pane_id = celerity_generate_unique_node_id();
    Javelin::initBehavior(
      'differential-keyboard-navigation',
      array(
        'haunt' => $pane_id,
      ));

    $draft = id(new PhabricatorDraft())->loadOneWhere(
      'authorPHID = %s AND draftKey = %s',
      $user->getPHID(),
      'diffusion-audit-'.$commit->getID());
    if ($draft) {
      $draft = $draft->getDraft();
    } else {
      $draft = null;
    }

    $actions = $this->getAuditActions($commit, $audit_requests);

    $mailable_source = new PhabricatorMetaMTAMailableDatasource();
    $auditor_source = new DiffusionAuditorDatasource();

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction('/audit/addcomment/')
      ->addHiddenInput('commit', $commit->getPHID())
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Action'))
          ->setName('action')
          ->setID('audit-action')
          ->setOptions($actions))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Add Auditors'))
          ->setName('auditors')
          ->setControlID('add-auditors')
          ->setControlStyle('display: none')
          ->setID('add-auditors-tokenizer')
          ->setDisableBehavior(true)
          ->setDatasource($auditor_source))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Add CCs'))
          ->setName('ccs')
          ->setControlID('add-ccs')
          ->setControlStyle('display: none')
          ->setID('add-ccs-tokenizer')
          ->setDisableBehavior(true)
          ->setDatasource($mailable_source))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setLabel(pht('Comments'))
          ->setName('content')
          ->setValue($draft)
          ->setID('audit-content')
          ->setUser($user))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Submit')));

    $header = new PHUIHeaderView();
    $header->setHeader(
      $is_serious ? pht('Audit Commit') : pht('Creative Accounting'));

    Javelin::initBehavior(
      'differential-add-reviewers-and-ccs',
      array(
        'dynamic' => array(
          'add-auditors-tokenizer' => array(
            'actions' => array('add_auditors' => 1),
            'src' => $auditor_source->getDatasourceURI(),
            'row' => 'add-auditors',
            'placeholder' => $auditor_source->getPlaceholderText(),
          ),
          'add-ccs-tokenizer' => array(
            'actions' => array('add_ccs' => 1),
            'src' => $mailable_source->getDatasourceURI(),
            'row' => 'add-ccs',
            'placeholder' => $mailable_source->getPlaceholderText(),
          ),
        ),
        'select' => 'audit-action',
      ));

    Javelin::initBehavior('differential-feedback-preview', array(
      'uri'       => '/audit/preview/'.$commit->getID().'/',
      'preview'   => 'audit-preview',
      'content'   => 'audit-content',
      'action'    => 'audit-action',
      'previewTokenizers' => array(
        'auditors' => 'add-auditors-tokenizer',
        'ccs'      => 'add-ccs-tokenizer',
      ),
      'inline'     => 'inline-comment-preview',
      'inlineuri'  => '/diffusion/inline/preview/'.$commit->getPHID().'/',
    ));

    $loading = phutil_tag_div(
      'aphront-panel-preview-loading-text',
      pht('Loading preview...'));

    $preview_panel = phutil_tag_div(
      'aphront-panel-preview aphront-panel-flush',
      array(
        phutil_tag('div', array('id' => 'audit-preview'), $loading),
        phutil_tag('div', array('id' => 'inline-comment-preview')),
      ));

    // TODO: This is pretty awkward, unify the CSS between Diffusion and
    // Differential better.
    require_celerity_resource('differential-core-view-css');

    $anchor = id(new PhabricatorAnchorView())
      ->setAnchorName('comment')
      ->setNavigationMarker(true)
      ->render();

    $comment_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($form);

    return phutil_tag(
      'div',
      array(
        'id' => $pane_id,
      ),
      phutil_tag_div(
        'differential-add-comment-panel',
        array($anchor, $comment_box, $preview_panel)));
  }

  /**
   * Return a map of available audit actions for rendering into a <select />.
   * This shows the user valid actions, and does not show nonsense/invalid
   * actions (like closing an already-closed commit, or resigning from a commit
   * you have no association with).
   */
  private function getAuditActions(
    PhabricatorRepositoryCommit $commit,
    array $audit_requests) {
    assert_instances_of($audit_requests, 'PhabricatorRepositoryAuditRequest');
    $user = $this->getRequest()->getUser();

    $user_is_author = ($commit->getAuthorPHID() == $user->getPHID());

    $user_request = null;
    foreach ($audit_requests as $audit_request) {
      if ($audit_request->getAuditorPHID() == $user->getPHID()) {
        $user_request = $audit_request;
        break;
      }
    }

    $actions = array();
    $actions[PhabricatorAuditActionConstants::COMMENT] = true;
    $actions[PhabricatorAuditActionConstants::ADD_CCS] = true;
    $actions[PhabricatorAuditActionConstants::ADD_AUDITORS] = true;

    // We allow you to accept your own commits. A use case here is that you
    // notice an issue with your own commit and "Raise Concern" as an indicator
    // to other auditors that you're on top of the issue, then later resolve it
    // and "Accept". You can not accept on behalf of projects or packages,
    // however.
    $actions[PhabricatorAuditActionConstants::ACCEPT]  = true;
    $actions[PhabricatorAuditActionConstants::CONCERN] = true;


    // To resign, a user must have authority on some request and not be the
    // commit's author.
    if (!$user_is_author) {
      $may_resign = false;

      $authority_map = array_fill_keys($this->auditAuthorityPHIDs, true);
      foreach ($audit_requests as $request) {
        if (empty($authority_map[$request->getAuditorPHID()])) {
          continue;
        }
        $may_resign = true;
        break;
      }

      // If the user has already resigned, don't show "Resign...".
      $status_resigned = PhabricatorAuditStatusConstants::RESIGNED;
      if ($user_request) {
        if ($user_request->getAuditStatus() == $status_resigned) {
          $may_resign = false;
        }
      }

      if ($may_resign) {
        $actions[PhabricatorAuditActionConstants::RESIGN] = true;
      }
    }

    $status_concern = PhabricatorAuditCommitStatusConstants::CONCERN_RAISED;
    $concern_raised = ($commit->getAuditStatus() == $status_concern);
    $can_close_option = PhabricatorEnv::getEnvConfig(
      'audit.can-author-close-audit');
    if ($can_close_option && $user_is_author && $concern_raised) {
      $actions[PhabricatorAuditActionConstants::CLOSE] = true;
    }

    foreach ($actions as $constant => $ignored) {
      $actions[$constant] =
        PhabricatorAuditActionConstants::getActionName($constant);
    }

    return $actions;
  }

  private function buildMergesTable(PhabricatorRepositoryCommit $commit) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // These aren't supported under SVN.
        return null;
    }

    $limit = 50;

    $merges = $this->callConduitWithDiffusionRequest(
      'diffusion.mergedcommitsquery',
      array(
        'commit' => $drequest->getCommit(),
        'limit' => $limit + 1,
      ));

    if (!$merges) {
      return null;
    }
    $merges = DiffusionPathChange::newFromConduit($merges);

    $caption = null;
    if (count($merges) > $limit) {
      $merges = array_slice($merges, 0, $limit);
      $caption = new PHUIInfoView();
      $caption->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
      $caption->appendChild(
        pht(
          'This commit merges a very large number of changes. Only the first '.
          '%s are shown.',
          new PhutilNumber($limit)));
    }

    $history_table = new DiffusionHistoryTableView();
    $history_table->setUser($this->getRequest()->getUser());
    $history_table->setDiffusionRequest($drequest);
    $history_table->setHistory($merges);
    $history_table->loadRevisions();

    $phids = $history_table->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $history_table->setHandles($handles);

    $panel = new PHUIObjectBoxView();
    $panel->setHeaderText(pht('Merged Changes'));
    $panel->appendChild($history_table);
    if ($caption) {
      $panel->setInfoView($caption);
    }

    return $panel;
  }

  private function renderHeadsupActionList(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepository $repository) {

    $request = $this->getRequest();
    $user = $request->getUser();

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObject($commit)
      ->setObjectURI($request->getRequestURI());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $commit,
      PhabricatorPolicyCapability::CAN_EDIT);

    $uri = '/diffusion/'.$repository->getCallsign().'/commit/'.
           $commit->getCommitIdentifier().'/edit/';

    $action = id(new PhabricatorActionView())
      ->setName(pht('Edit Commit'))
      ->setHref($uri)
      ->setIcon('fa-pencil')
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);
    $actions->addAction($action);

    require_celerity_resource('phabricator-object-selector-css');
    require_celerity_resource('javelin-behavior-phabricator-object-selector');

    $maniphest = 'PhabricatorManiphestApplication';
    if (PhabricatorApplication::isClassInstalled($maniphest)) {
      $action = id(new PhabricatorActionView())
        ->setName(pht('Edit Maniphest Tasks'))
        ->setIcon('fa-anchor')
        ->setHref('/search/attach/'.$commit->getPHID().'/TASK/edge/')
        ->setWorkflow(true)
        ->setDisabled(!$can_edit);
      $actions->addAction($action);
    }

    $action = id(new PhabricatorActionView())
      ->setName(pht('Download Raw Diff'))
      ->setHref($request->getRequestURI()->alter('diff', true))
      ->setIcon('fa-download');
    $actions->addAction($action);

    return $actions;
  }

  private function buildRefs(DiffusionRequest $request) {
    // this is git-only, so save a conduit round trip and just get out of
    // here if the repository isn't git
    $type_git = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;
    $repository = $request->getRepository();
    if ($repository->getVersionControlSystem() != $type_git) {
      return null;
    }

    $results = $this->callConduitWithDiffusionRequest(
      'diffusion.refsquery',
      array('commit' => $request->getCommit()));
    $ref_links = array();
    foreach ($results as $ref_data) {
      $ref_links[] = phutil_tag('a',
        array('href' => $ref_data['href']),
        $ref_data['ref']);
    }
    return phutil_implode_html(', ', $ref_links);
  }

  private function buildRawDiffResponse(DiffusionRequest $drequest) {
    $raw_diff = $this->callConduitWithDiffusionRequest(
      'diffusion.rawdiffquery',
      array(
        'commit' => $drequest->getCommit(),
        'path' => $drequest->getPath(),
      ));

    $file = PhabricatorFile::buildFromFileDataOrHash(
      $raw_diff,
      array(
        'name' => $drequest->getCommit().'.diff',
        'ttl' => (60 * 60 * 24),
        'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
      ));

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $file->attachToObject($drequest->getRepository()->getPHID());
    unset($unguarded);

    return $file->getRedirectResponse();
  }

  private function renderAuditStatusView(array $audit_requests) {
    assert_instances_of($audit_requests, 'PhabricatorRepositoryAuditRequest');
    $viewer = $this->getViewer();

    $authority_map = array_fill_keys($this->auditAuthorityPHIDs, true);

    $view = new PHUIStatusListView();
    foreach ($audit_requests as $request) {
      $code = $request->getAuditStatus();
      $item = new PHUIStatusItemView();
      $item->setIcon(
        PhabricatorAuditStatusConstants::getStatusIcon($code),
        PhabricatorAuditStatusConstants::getStatusColor($code),
        PhabricatorAuditStatusConstants::getStatusName($code));

      $note = array();
      foreach ($request->getAuditReasons() as $reason) {
        $note[] = phutil_tag('div', array(), $reason);
      }
      $item->setNote($note);

      $auditor_phid = $request->getAuditorPHID();
      $target = $viewer->renderHandle($auditor_phid);
      $item->setTarget($target);

      if (isset($authority_map[$auditor_phid])) {
        $item->setHighlighted(true);
      }

      $view->addItem($item);
    }

    return $view;
  }

  private function linkBugtraq($corpus) {
    $url = PhabricatorEnv::getEnvConfig('bugtraq.url');
    if (!strlen($url)) {
      return $corpus;
    }

    $regexes = PhabricatorEnv::getEnvConfig('bugtraq.logregex');
    if (!$regexes) {
      return $corpus;
    }

    $parser = id(new PhutilBugtraqParser())
      ->setBugtraqPattern("[[ {$url} | %BUGID% ]]")
      ->setBugtraqCaptureExpression(array_shift($regexes));

    $select = array_shift($regexes);
    if ($select) {
      $parser->setBugtraqSelectExpression($select);
    }

    return $parser->processCorpus($corpus);
  }

}
