<?php

final class DiffusionCommitController extends DiffusionController {

  const CHANGES_LIMIT = 100;

  private $auditAuthorityPHIDs;
  private $highlightedAudits;

  public function willProcessRequest(array $data) {
    // This controller doesn't use blob/path stuff, just pass the dictionary
    // in directly instead of using the AphrontRequest parsing mechanism.
    $drequest = DiffusionRequest::newFromDictionary($data);
    $this->diffusionRequest = $drequest;
  }

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->getStr('diff')) {
      return $this->buildRawDiffResponse($drequest);
    }

    $callsign = $drequest->getRepository()->getCallsign();

    $content = array();
    $repository = $drequest->getRepository();
    $commit = $drequest->loadCommit();

    if (!$commit) {
      $exists = $this->callConduitWithDiffusionRequest(
        'diffusion.existsquery',
        array('commit' => $drequest->getCommit()));
      if (!$exists) {
        return new Aphront404Response();
      }
      return $this->buildStandardPageResponse(
        id(new AphrontErrorView())
        ->setTitle('Error displaying commit.')
        ->appendChild('Failed to load the commit because the commit has not '.
                      'been parsed yet.'),
          array('title' => 'Commit Still Parsing'));
    }

    $commit_data = $drequest->loadCommitData();
    $commit->attachCommitData($commit_data);

    $top_anchor = id(new PhabricatorAnchorView())
      ->setAnchorName('top')
      ->setNavigationMarker(true);

    $is_foreign = $commit_data->getCommitDetail('foreign-svn-stub');
    $changesets = null;
    if ($is_foreign) {
      $subpath = $commit_data->getCommitDetail('svn-subpath');

      $error_panel = new AphrontErrorView();
      $error_panel->setTitle('Commit Not Tracked');
      $error_panel->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $error_panel->appendChild(
        "This Diffusion repository is configured to track only one ".
        "subdirectory of the entire Subversion repository, and this commit ".
        "didn't affect the tracked subdirectory ('".$subpath."'), so no ".
        "information is available.");
      $content[] = $error_panel;
      $content[] = $top_anchor;
    } else {
      $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();
      $engine->setConfig('viewer', $user);

      require_celerity_resource('diffusion-commit-view-css');
      require_celerity_resource('phabricator-remarkup-css');

      $parent_query = DiffusionCommitParentsQuery::newFromDiffusionRequest(
        $drequest);

      $headsup_view = id(new PhabricatorHeaderView())
        ->setHeader(nonempty($commit->getSummary(), pht('Commit Detail')));

      $headsup_actions = $this->renderHeadsupActionList($commit, $repository);

      $commit_properties = $this->loadCommitProperties(
        $commit,
        $commit_data,
        $parent_query->loadParents());
      $property_list = id(new PhabricatorPropertyListView())
        ->setHasKeyboardShortcuts(true)
        ->setUser($user)
        ->setObject($commit);
      foreach ($commit_properties as $key => $value) {
        $property_list->addProperty($key, $value);
      }

      $message = $commit_data->getCommitMessage();

      $revision = $commit->getCommitIdentifier();
      $message = $repository->linkBugtraq($message, $revision);

      $message = $engine->markupText($message);

      $property_list->invokeWillRenderEvent();
      $property_list->addTextContent(
        phutil_tag(
          'div',
          array(
            'class' => 'diffusion-commit-message phabricator-remarkup',
          ),
          $message));
      $content[] = $top_anchor;
      $content[] = $headsup_view;
      $content[] = $headsup_actions;
      $content[] = $property_list;
    }

    $query = new PhabricatorAuditQuery();
    $query->withCommitPHIDs(array($commit->getPHID()));
    $audit_requests = $query->execute();

    $this->auditAuthorityPHIDs =
      PhabricatorAuditCommentEditor::loadAuditPHIDsForUser($user);

    $content[] = $this->buildAuditTable($commit, $audit_requests);
    $content[] = $this->buildComments($commit);

    $hard_limit = 1000;

    $change_query = DiffusionPathChangeQuery::newFromDiffusionRequest(
      $drequest);
    $change_query->setLimit($hard_limit + 1);
    $changes = $change_query->loadChanges();

    $was_limited = (count($changes) > $hard_limit);
    if ($was_limited) {
      $changes = array_slice($changes, 0, $hard_limit);
    }

    $content[] = $this->buildMergesTable($commit);

    $owners_paths = array();
    if ($this->highlightedAudits) {
      $packages = id(new PhabricatorOwnersPackage())->loadAllWhere(
        'phid IN (%Ls)',
        mpull($this->highlightedAudits, 'getAuditorPHID'));
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
      $error_panel = new AphrontErrorView();
      $error_panel->setTitle('Bad Commit');
      $error_panel->appendChild($bad_commit['description']);

      $content[] = $error_panel;
    } else if ($is_foreign) {
      // Don't render anything else.
    } else if (!count($changes)) {
      $no_changes = new AphrontErrorView();
      $no_changes->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $no_changes->setTitle('Not Yet Parsed');
      // TODO: This can also happen with weird SVN changes that don't do
      // anything (or only alter properties?), although the real no-changes case
      // is extremely rare and might be impossible to produce organically. We
      // should probably write some kind of "Nothing Happened!" change into the
      // DB once we parse these changes so we can distinguish between
      // "not parsed yet" and "no changes".
      $no_changes->appendChild(
        "This commit hasn't been fully parsed yet (or doesn't affect any ".
        "paths).");
      $content[] = $no_changes;
    } else if ($was_limited) {
      $huge_commit = new AphrontErrorView();
      $huge_commit->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $huge_commit->setTitle(pht('Enormous Commit'));
      $huge_commit->appendChild(
        pht(
          'This commit is enormous, and affects more than %d files. '.
          'Changes are not shown.',
          $hard_limit));
      $content[] = $huge_commit;
    } else {
      $change_panel = new AphrontPanelView();
      $change_panel->setHeader("Changes (".number_format($count).")");
      $change_panel->setID('toc');
      if ($count > self::CHANGES_LIMIT) {
        $show_all_button = phutil_tag(
          'a',
          array(
            'class'   => 'button green',
            'href'    => '?show_all=true',
          ),
          'Show All Changes');
        $warning_view = id(new AphrontErrorView())
          ->setSeverity(AphrontErrorView::SEVERITY_WARNING)
          ->setTitle('Very Large Commit')
          ->appendChild(phutil_tag(
            'p',
            array(),
            "This commit is very large. Load each file individually."));

        $change_panel->appendChild($warning_view);
        $change_panel->addButton($show_all_button);
      }

      $change_panel->appendChild($change_table);
      $change_panel->setNoBackground();

      $content[] = $change_panel;

      $changesets = DiffusionPathChange::convertToDifferentialChangesets(
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
          throw new Exception("Unknown VCS.");
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

      if ($count <= self::CHANGES_LIMIT) {
        $visible_changesets = $changesets;
      } else {
        $visible_changesets = array();
        $inlines = id(new PhabricatorAuditInlineComment())->loadAllWhere(
          'commitPHID = %s AND (auditCommentID IS NOT NULL OR authorPHID = %s)',
          $commit->getPHID(),
          $user->getPHID());
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
      // pick the first branch for "Browse in Diffusion" View Option
      $branches     = $commit_data->getCommitDetail('seenOnBranches', array());
      $first_branch = reset($branches);
      $change_list->setBranch($first_branch);

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

    $crumbs = $this->buildCrumbs(array(
      'commit' => true,
    ));

    $prefs = $user->loadPreferences();
    $pref_filetree = PhabricatorUserPreferences::PREFERENCE_DIFF_FILETREE;
    $pref_collapse = PhabricatorUserPreferences::PREFERENCE_NAV_COLLAPSED;
    $show_filetree = $prefs->getPreference($pref_filetree);
    $collapsed = $prefs->getPreference($pref_collapse);

    if ($changesets && $show_filetree) {
      $nav = id(new DifferentialChangesetFileTreeSideNavBuilder())
        ->setAnchorName('top')
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
        'title' => $commit_id
      ));
  }

  private function loadCommitProperties(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data,
    array $parents) {

    assert_instances_of($parents, 'PhabricatorRepositoryCommit');
    $user = $this->getRequest()->getUser();
    $commit_phid = $commit->getPHID();

    $edge_query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($commit_phid))
      ->withEdgeTypes(array(
        PhabricatorEdgeConfig::TYPE_COMMIT_HAS_TASK,
        PhabricatorEdgeConfig::TYPE_COMMIT_HAS_PROJECT,
        PhabricatorEdgeConfig::TYPE_COMMIT_HAS_DREV,
      ));

    $edges = $edge_query->execute();

    $task_phids = array_keys(
      $edges[$commit_phid][PhabricatorEdgeConfig::TYPE_COMMIT_HAS_TASK]);
    $proj_phids = array_keys(
      $edges[$commit_phid][PhabricatorEdgeConfig::TYPE_COMMIT_HAS_PROJECT]);
    $revision_phid = key(
      $edges[$commit_phid][PhabricatorEdgeConfig::TYPE_COMMIT_HAS_DREV]);

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

    $handles = array();
    if ($phids) {
      $handles = $this->loadViewerHandles($phids);
    }

    $props = array();

    if ($commit->getAuditStatus()) {
      $status = PhabricatorAuditCommitStatusConstants::getStatusName(
        $commit->getAuditStatus());
      $props['Status'] = phutil_tag(
        'strong',
        array(),
        $status);
    }

    $props['Committed'] = phabricator_datetime($commit->getEpoch(), $user);

    $author_phid = $data->getCommitDetail('authorPHID');
    if ($data->getCommitDetail('authorPHID')) {
      $props['Author'] = $handles[$author_phid]->renderLink();
    } else {
      $props['Author'] = $data->getAuthorName();
    }

    $reviewer_phid = $data->getCommitDetail('reviewerPHID');
    if ($reviewer_phid) {
      $props['Reviewer'] = $handles[$reviewer_phid]->renderLink();
    }

    $committer = $data->getCommitDetail('committer');
    if ($committer) {
      $committer_phid = $data->getCommitDetail('committerPHID');
      if ($data->getCommitDetail('committerPHID')) {
        $props['Committer'] = $handles[$committer_phid]->renderLink();
      } else {
        $props['Committer'] = $committer;
      }
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

    $request = $this->getDiffusionRequest();

    $props['Branches'] = phutil_tag(
      'span',
      array(
        'id' => 'commit-branches',
      ),
      'Unknown');
    $props['Tags'] = phutil_tag(
      'span',
      array(
        'id' => 'commit-tags',
      ),
      'Unknown');

    $callsign = $request->getRepository()->getCallsign();
    $root = '/diffusion/'.$callsign.'/commit/'.$commit->getCommitIdentifier();
    Javelin::initBehavior(
      'diffusion-commit-branches',
      array(
        $root.'/branches/' => 'commit-branches',
        $root.'/tags/' => 'commit-tags',
      ));

    $refs = $this->buildRefs($request);
    if ($refs) {
      $props['References'] = $refs;
    }

    if ($task_phids) {
      $task_list = array();
      foreach ($task_phids as $phid) {
        $task_list[] = $handles[$phid]->renderLink();
      }
      $task_list = phutil_implode_html(phutil_tag('br'), $task_list);
      $props['Tasks'] = $task_list;
    }

    if ($proj_phids) {
      $proj_list = array();
      foreach ($proj_phids as $phid) {
        $proj_list[] = $handles[$phid]->renderLink();
      }
      $proj_list = phutil_implode_html(phutil_tag('br'), $proj_list);
      $props['Projects'] = $proj_list;
    }

    return $props;
  }

  private function buildAuditTable(
    PhabricatorRepositoryCommit $commit,
    array $audits) {
    assert_instances_of($audits, 'PhabricatorRepositoryAuditRequest');
    $user = $this->getRequest()->getUser();

    $view = new PhabricatorAuditListView();
    $view->setAudits($audits);
    $view->setCommits(array($commit));
    $view->setUser($user);
    $view->setShowCommits(false);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);
    $view->setAuthorityPHIDs($this->auditAuthorityPHIDs);
    $this->highlightedAudits = $view->getHighlightedAudits();

    $panel = new AphrontPanelView();
    $panel->setHeader('Audits');
    $panel->setCaption('Audits you are responsible for are highlighted.');
    $panel->appendChild($view);
    $panel->setNoBackground();

    return $panel;
  }

  private function buildComments(PhabricatorRepositoryCommit $commit) {
    $user = $this->getRequest()->getUser();
    $comments = id(new PhabricatorAuditComment())->loadAllWhere(
      'targetPHID = %s ORDER BY dateCreated ASC',
      $commit->getPHID());

    $inlines = id(new PhabricatorAuditInlineComment())->loadAllWhere(
      'commitPHID = %s AND auditCommentID IS NOT NULL',
      $commit->getPHID());

    $path_ids = mpull($inlines, 'getPathID');

    $path_map = array();
    if ($path_ids) {
      $path_map = id(new DiffusionPathQuery())
        ->withPathIDs($path_ids)
        ->execute();
      $path_map = ipull($path_map, 'path', 'id');
    }

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);

    foreach ($comments as $comment) {
      $engine->addObject(
        $comment,
        PhabricatorAuditComment::MARKUP_FIELD_BODY);
    }

    foreach ($inlines as $inline) {
      $engine->addObject(
        $inline,
        PhabricatorInlineCommentInterface::MARKUP_FIELD_BODY);
    }

    $engine->process();

    $view = new DiffusionCommentListView();
    $view->setMarkupEngine($engine);
    $view->setUser($user);
    $view->setComments($comments);
    $view->setInlineComments($inlines);
    $view->setPathMap($path_map);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    return $view;
  }

  private function renderAddCommentPanel(
    PhabricatorRepositoryCommit $commit,
    array $audit_requests) {
    assert_instances_of($audit_requests, 'PhabricatorRepositoryAuditRequest');
    $user = $this->getRequest()->getUser();

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

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction('/audit/addcomment/')
      ->addHiddenInput('commit', $commit->getPHID())
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Action')
          ->setName('action')
          ->setID('audit-action')
          ->setOptions($actions))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('Add Auditors')
          ->setName('auditors')
          ->setControlID('add-auditors')
          ->setControlStyle('display: none')
          ->setID('add-auditors-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('Add CCs')
          ->setName('ccs')
          ->setControlID('add-ccs')
          ->setControlStyle('display: none')
          ->setID('add-ccs-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setLabel('Comments')
          ->setName('content')
          ->setValue($draft)
          ->setID('audit-content')
          ->setUser($user))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($is_serious ? 'Submit' : 'Cook the Books'));

    $panel = new AphrontPanelView();
    $panel->setHeader($is_serious ? 'Audit Commit' : 'Creative Accounting');
    $panel->appendChild($form);
    $panel->addClass('aphront-panel-accent');
    $panel->addClass('aphront-panel-flush');

    require_celerity_resource('phabricator-transaction-view-css');

    Javelin::initBehavior(
      'differential-add-reviewers-and-ccs',
      array(
        'dynamic' => array(
          'add-auditors-tokenizer' => array(
            'actions' => array('add_auditors' => 1),
            'src' => '/typeahead/common/users/',
            'row' => 'add-auditors',
            'ondemand' => PhabricatorEnv::getEnvConfig('tokenizer.ondemand'),
            'placeholder' => 'Type a user name...',
          ),
          'add-ccs-tokenizer' => array(
            'actions' => array('add_ccs' => 1),
            'src' => '/typeahead/common/mailable/',
            'row' => 'add-ccs',
            'ondemand' => PhabricatorEnv::getEnvConfig('tokenizer.ondemand'),
            'placeholder' => 'Type a user or mailing list...',
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

    $preview_panel = hsprintf(
      '<div class="aphront-panel-preview aphront-panel-flush">
        <div id="audit-preview">
          <div class="aphront-panel-preview-loading-text">
            Loading preview...
          </div>
        </div>
        <div id="inline-comment-preview">
        </div>
      </div>');

    // TODO: This is pretty awkward, unify the CSS between Diffusion and
    // Differential better.
    require_celerity_resource('differential-core-view-css');

    return phutil_tag(
      'div',
      array(
        'id' => $pane_id,
      ),
      hsprintf(
        '<div class="differential-add-comment-panel">%s%s%s</div>',
        id(new PhabricatorAnchorView())
          ->setAnchorName('comment')
          ->setNavigationMarker(true)
          ->render(),
        $panel->render(),
        $preview_panel));
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

    $limit = 50;

    $merge_query = DiffusionMergedCommitsQuery::newFromDiffusionRequest(
      $drequest);
    $merge_query->setLimit($limit + 1);
    $merges = $merge_query->loadMergedCommits();

    if (!$merges) {
      return null;
    }

    $caption = null;
    if (count($merges) > $limit) {
      $merges = array_slice($merges, 0, $limit);
      $caption =
        "This commit merges more than {$limit} changes. Only the first ".
        "{$limit} are shown.";
    }

    $history_table = new DiffusionHistoryTableView();
    $history_table->setUser($this->getRequest()->getUser());
    $history_table->setDiffusionRequest($drequest);
    $history_table->setHistory($merges);
    $history_table->loadRevisions();

    $phids = $history_table->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $history_table->setHandles($handles);

    $panel = new AphrontPanelView();
    $panel->setHeader('Merged Changes');
    $panel->setCaption($caption);
    $panel->appendChild($history_table);
    $panel->setNoBackground();

    return $panel;
  }

  private function renderHeadsupActionList(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepository $repository) {

    $request = $this->getRequest();
    $user = $request->getUser();

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObject($commit);

    // TODO -- integrate permissions into whether or not this action is shown
    $uri = '/diffusion/'.$repository->getCallSign().'/commit/'.
           $commit->getCommitIdentifier().'/edit/';

    $action = id(new PhabricatorActionView())
      ->setName('Edit Commit')
      ->setHref($uri)
      ->setIcon('edit');
    $actions->addAction($action);

    require_celerity_resource('phabricator-object-selector-css');
    require_celerity_resource('javelin-behavior-phabricator-object-selector');

    $maniphest = 'PhabricatorApplicationManiphest';
    if (PhabricatorApplication::isClassInstalled($maniphest)) {
      $action = id(new PhabricatorActionView())
        ->setName('Edit Maniphest Tasks')
        ->setIcon('attach')
        ->setHref('/search/attach/'.$commit->getPHID().'/TASK/edge/')
        ->setWorkflow(true);
      $actions->addAction($action);
    }

    if ($user->getIsAdmin()) {
      $action = id(new PhabricatorActionView())
        ->setName('MetaMTA Transcripts')
        ->setIcon('file')
        ->setHref('/mail/?phid='.$commit->getPHID());
      $actions->addAction($action);
    }

    $action = id(new PhabricatorActionView())
      ->setName('Herald Transcripts')
      ->setIcon('file')
      ->setHref('/herald/transcript/?phid='.$commit->getPHID())
      ->setWorkflow(true);
    $actions->addAction($action);

    $action = id(new PhabricatorActionView())
      ->setName('Download Raw Diff')
      ->setHref($request->getRequestURI()->alter('diff', true))
      ->setIcon('download');
    $actions->addAction($action);

    return $actions;
  }

  private function buildRefs(DiffusionRequest $request) {
    // Not turning this into a proper Query class since it's pretty simple,
    // one-off, and Git-specific.

    $type_git = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;

    $repository = $request->getRepository();
    if ($repository->getVersionControlSystem() != $type_git) {
      return null;
    }

    list($stdout) = $repository->execxLocalCommand(
      'log --format=%s -n 1 %s --',
      '%d',
      $request->getCommit());

    // %d, gives a weird output format
    // similar to (remote/one, remote/two, remote/three)
    $refs = trim($stdout, "() \n");
    if (!$refs) {
        return null;
    }
    $refs = explode(',', $refs);
    $refs = array_map('trim', $refs);

    $ref_links = array();
    foreach ($refs as $ref) {
      $ref_links[] = phutil_tag(
        'a',
        array(
          'href' => $request->generateURI(
            array(
              'action'  => 'browse',
              'branch'  => $ref,
            )),
        ),
        $ref);
    }

    return phutil_implode_html(', ', $ref_links);
  }

  private function buildRawDiffResponse(DiffusionRequest $drequest) {
    $raw_query = DiffusionRawDiffQuery::newFromDiffusionRequest($drequest);
    $raw_diff  = $raw_query->loadRawDiff();

    $file = PhabricatorFile::buildFromFileDataOrHash(
      $raw_diff,
      array(
        'name' => $drequest->getCommit().'.diff',
      ));

    return id(new AphrontRedirectResponse())->setURI($file->getBestURI());
  }

}
