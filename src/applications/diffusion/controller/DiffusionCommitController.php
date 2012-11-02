<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
    $content[] = $this->buildCrumbs(array(
      'commit' => true,
    ));

    $repository = $drequest->getRepository();
    $commit = $drequest->loadCommit();

    if (!$commit) {
      $query = DiffusionExistsQuery::newFromDiffusionRequest($drequest);
      $exists = $query->loadExistentialData();
      if (!$exists) {
        return new Aphront404Response();
      }
      return $this->buildStandardPageResponse(
        id(new AphrontErrorView())
        ->setTitle('Error displaying commit.')
        ->appendChild('Failed to load the commit because the commit has not '.
                      'been parsed yet.'),
          array('title' => 'Commit Still Parsing')
        );
    }

    $commit_data = $drequest->loadCommitData();
    $commit->attachCommitData($commit_data);

    $is_foreign = $commit_data->getCommitDetail('foreign-svn-stub');
    if ($is_foreign) {
      $subpath = $commit_data->getCommitDetail('svn-subpath');

      $error_panel = new AphrontErrorView();
      $error_panel->setTitle('Commit Not Tracked');
      $error_panel->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $error_panel->appendChild(
        "This Diffusion repository is configured to track only one ".
        "subdirectory of the entire Subversion repository, and this commit ".
        "didn't affect the tracked subdirectory ('".
        phutil_escape_html($subpath)."'), so no information is available.");
      $content[] = $error_panel;
    } else {
      $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();

      require_celerity_resource('diffusion-commit-view-css');
      require_celerity_resource('phabricator-remarkup-css');

      $parent_query = DiffusionCommitParentsQuery::newFromDiffusionRequest(
        $drequest);

      $headsup_panel = new AphrontHeadsupView();
      $headsup_panel->setHeader('Commit Detail');
      $headsup_panel->setActionList(
        $this->renderHeadsupActionList($commit, $repository));
      $headsup_panel->setProperties(
        $this->getCommitProperties(
          $commit,
          $commit_data,
          $parent_query->loadParents()));

      $headsup_panel->appendChild(
        '<div class="diffusion-commit-message phabricator-remarkup">'.
          $engine->markupText($commit_data->getCommitMessage()).
        '</div>');

      $content[] = $headsup_panel;
    }

    $query = new PhabricatorAuditQuery();
    $query->withCommitPHIDs(array($commit->getPHID()));
    $audit_requests = $query->execute();

    $this->auditAuthorityPHIDs =
      PhabricatorAuditCommentEditor::loadAuditPHIDsForUser($user);

    $content[] = $this->buildAuditTable($commit, $audit_requests);
    $content[] = $this->buildComments($commit);

    $change_query = DiffusionPathChangeQuery::newFromDiffusionRequest(
      $drequest);
    $changes = $change_query->loadChanges();

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

    $pane_id = null;
    if ($bad_commit) {
      $error_panel = new AphrontErrorView();
      $error_panel->setTitle('Bad Commit');
      $error_panel->appendChild(
        phutil_escape_html($bad_commit['description']));

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
    } else {
      $change_panel = new AphrontPanelView();
      $change_panel->setHeader("Changes (".number_format($count).")");
      $change_panel->setID('toc');

      if ($count > self::CHANGES_LIMIT) {
        $show_all_button = phutil_render_tag(
          'a',
          array(
            'class'   => 'button green',
            'href'    => '?show_all=true',
          ),
          phutil_escape_html('Show All Changes'));
        $warning_view = id(new AphrontErrorView())
          ->setSeverity(AphrontErrorView::SEVERITY_WARNING)
          ->setTitle('Very Large Commit')
          ->appendChild(
            "<p>This commit is very large. Load each file individually.</p>");

        $change_panel->appendChild($warning_view);
        $change_panel->addButton($show_all_button);
      }

      $change_panel->appendChild($change_table);

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

      $change_list = new DifferentialChangesetListView();
      $change_list->setChangesets($changesets);
      $change_list->setVisibleChangesets($visible_changesets);
      $change_list->setRenderingReferences($references);
      $change_list->setRenderURI('/diffusion/'.$callsign.'/diff/');
      $change_list->setRepository($repository);
      $change_list->setUser($user);
      // pick the first branch for "Browse in Diffusion" View Option
      $branches     = $commit_data->getCommitDetail('seenOnBranches');
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

      // TODO: This is pretty awkward, unify the CSS between Diffusion and
      // Differential better.
      require_celerity_resource('differential-core-view-css');
      $pane_id = celerity_generate_unique_node_id();
      $add_comment_view = $this->renderAddCommentPanel($commit,
                                                       $audit_requests,
                                                       $pane_id);
      $main_pane = phutil_render_tag(
        'div',
        array(
          'class' => 'differential-primary-pane',
          'id'    => $pane_id
        ),
        $change_list->render().
        $add_comment_view);

      $content[] = $main_pane;
    }

    return $this->buildStandardPageResponse(
      $content,
      array(
        'title' => 'r'.$callsign.$commit->getCommitIdentifier(),
      ));
  }

  private function getCommitProperties(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data,
    array $parents) {

    assert_instances_of($parents, 'PhabricatorRepositoryCommit');
    $user = $this->getRequest()->getUser();
    $commit_phid = $commit->getPHID();

    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($commit_phid))
      ->withEdgeTypes(array(
        PhabricatorEdgeConfig::TYPE_COMMIT_HAS_TASK,
        PhabricatorEdgeConfig::TYPE_COMMIT_HAS_PROJECT
      ))
      ->execute();

    $task_phids = array_keys(
      $edges[$commit_phid][PhabricatorEdgeConfig::TYPE_COMMIT_HAS_TASK]
    );
    $proj_phids = array_keys(
      $edges[$commit_phid][PhabricatorEdgeConfig::TYPE_COMMIT_HAS_PROJECT]
    );

    $phids = array_merge($task_phids, $proj_phids);
    if ($data->getCommitDetail('authorPHID')) {
      $phids[] = $data->getCommitDetail('authorPHID');
    }
    if ($data->getCommitDetail('reviewerPHID')) {
      $phids[] = $data->getCommitDetail('reviewerPHID');
    }
    if ($data->getCommitDetail('committerPHID')) {
      $phids[] = $data->getCommitDetail('committerPHID');
    }
    if ($data->getCommitDetail('differential.revisionPHID')) {
      $phids[] = $data->getCommitDetail('differential.revisionPHID');
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
      $props['Status'] = phutil_render_tag(
        'strong',
        array(),
        phutil_escape_html($status));
    }

    $props['Committed'] = phabricator_datetime($commit->getEpoch(), $user);

    $author_phid = $data->getCommitDetail('authorPHID');
    if ($data->getCommitDetail('authorPHID')) {
      $props['Author'] = $handles[$author_phid]->renderLink();
    } else {
      $props['Author'] = phutil_escape_html($data->getAuthorName());
    }

    $reviewer_phid = $data->getCommitDetail('reviewerPHID');
    $reviewer_name = $data->getCommitDetail('reviewerName');
    if ($reviewer_phid) {
      $props['Reviewer'] = $handles[$reviewer_phid]->renderLink();
    } else if ($reviewer_name) {
      $props['Reviewer'] = phutil_escape_html($reviewer_name);
    }

    $committer = $data->getCommitDetail('committer');
    if ($committer) {
      $committer_phid = $data->getCommitDetail('committerPHID');
      if ($data->getCommitDetail('committerPHID')) {
        $props['Committer'] = $handles[$committer_phid]->renderLink();
      } else {
        $props['Committer'] = phutil_escape_html($committer);
      }
    }

    $revision_phid = $data->getCommitDetail('differential.revisionPHID');
    if ($revision_phid) {
      $props['Differential Revision'] = $handles[$revision_phid]->renderLink();
    }

    if ($parents) {
      $parent_links = array();
      foreach ($parents as $parent) {
        $parent_links[] = $handles[$parent->getPHID()]->renderLink();
      }
      $props['Parents'] = implode(' &middot; ', $parent_links);
    }

    $request = $this->getDiffusionRequest();

    $props['Branches'] = '<span id="commit-branches">Unknown</span>';
    $props['Tags'] = '<span id="commit-tags">Unknown</span>';

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
      $task_list = implode('<br />', $task_list);
      $props['Tasks'] = $task_list;
    }

    if ($proj_phids) {
      $proj_list = array();
      foreach ($proj_phids as $phid) {
        $proj_list[] = $handles[$phid]->renderLink();
      }
      $proj_list = implode('<br />', $proj_list);
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
    $view->setShowDescriptions(false);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);
    $view->setAuthorityPHIDs($this->auditAuthorityPHIDs);
    $this->highlightedAudits = $view->getHighlightedAudits();

    $panel = new AphrontPanelView();
    $panel->setHeader('Audits');
    $panel->setCaption('Audits you are responsible for are highlighted.');
    $panel->appendChild($view);

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
    array $audit_requests,
    $pane_id = null) {
    assert_instances_of($audit_requests, 'PhabricatorRepositoryAuditRequest');
    $user = $this->getRequest()->getUser();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

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
          ->setID('audit-content'))
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

    $preview_panel =
      '<div class="aphront-panel-preview aphront-panel-flush">
        <div id="audit-preview">
          <div class="aphront-panel-preview-loading-text">
            Loading preview...
          </div>
        </div>
        <div id="inline-comment-preview">
        </div>
      </div>';

    return
      phutil_render_tag(
        'div',
        array(
          'class' => 'differential-add-comment-panel',
        ),
        $panel->render().
        $preview_panel);
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

    if ($user_is_author && $concern_raised) {
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

    return $panel;
  }

  private function renderHeadsupActionList(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepository $repository) {

    $request = $this->getRequest();
    $user = $request->getUser();

    $actions = array();

    // TODO -- integrate permissions into whether or not this action is shown
    $uri = '/diffusion/'.$repository->getCallSign().'/commit/'.
           $commit->getCommitIdentifier().'/edit/';
    $action = new AphrontHeadsupActionView();
    $action->setClass('action-edit');
    $action->setURI($uri);
    $action->setName('Edit Commit');
    $action->setWorkflow(false);
    $actions[] = $action;

    require_celerity_resource('phabricator-flag-css');
    $flag = PhabricatorFlagQuery::loadUserFlag($user, $commit->getPHID());
    if ($flag) {
      $class = PhabricatorFlagColor::getCSSClass($flag->getColor());
      $color = PhabricatorFlagColor::getColorName($flag->getColor());

      $action = new AphrontHeadsupActionView();
      $action->setClass('flag-clear '.$class);
      $action->setURI('/flag/delete/'.$flag->getID().'/');
      $action->setName('Remove '.$color.' Flag');
      $action->setWorkflow(true);
      $actions[] = $action;
    } else {
      $action = new AphrontHeadsupActionView();
      $action->setClass('phabricator-flag-ghost');
      $action->setURI('/flag/edit/'.$commit->getPHID().'/');
      $action->setName('Flag Commit');
      $action->setWorkflow(true);
      $actions[] = $action;
    }

    require_celerity_resource('phabricator-object-selector-css');
    require_celerity_resource('javelin-behavior-phabricator-object-selector');

    if (PhabricatorEnv::getEnvConfig('maniphest.enabled')) {
      $action = new AphrontHeadsupActionView();
      $action->setName('Edit Maniphest Tasks');
      $action->setURI('/search/attach/'.$commit->getPHID().'/TASK/edge/');
      $action->setWorkflow(true);
      $action->setClass('attach-maniphest');
      $actions[] = $action;
    }

    if ($user->getIsAdmin()) {
      $action = new AphrontHeadsupActionView();
      $action->setName('MetaMTA Transcripts');
      $action->setURI('/mail/?phid='.$commit->getPHID());
      $action->setClass('transcripts-metamta');
      $actions[] = $action;
    }

    $action = new AphrontHeadsupActionView();
    $action->setName('Herald Transcripts');
    $action->setURI('/herald/transcript/?phid='.$commit->getPHID());
    $action->setClass('transcripts-herald');
    $actions[] = $action;

    $action = new AphrontHeadsupActionView();
    $action->setName('Download Raw Diff');
    $action->setURI($request->getRequestURI()->alter('diff', true));
    $action->setClass('action-download');
    $actions[] = $action;

    $action_list = new AphrontHeadsupActionListView();
    $action_list->setActions($actions);

    return $action_list;
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
      $ref_links[] = phutil_render_tag(
        'a',
        array(
          'href' => $request->generateURI(
            array(
              'action'  => 'browse',
              'branch'  => $ref,
            )),
        ),
        phutil_escape_html($ref));
    }
    $ref_links = implode(', ', $ref_links);
    return $ref_links;
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
