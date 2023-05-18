<?php

final class DiffusionCommitController extends DiffusionController {

  const CHANGES_LIMIT = 100;

  private $commitParents;
  private $commitRefs;
  private $commitMerges;
  private $commitErrors;
  private $commitExists;

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $drequest = $this->getDiffusionRequest();
    $viewer = $request->getUser();
    $repository = $drequest->getRepository();
    $commit_identifier = $drequest->getCommit();

    // If this page is being accessed via "/source/xyz/commit/...", redirect
    // to the canonical URI.
    $repo_callsign = $request->getURIData('repositoryCallsign');
    $has_callsign = $repo_callsign !== null && strlen($repo_callsign);
    $repo_id = $request->getURIData('repositoryID');
    $has_id = $repo_id !== null && strlen($repo_id);

    if (!$has_callsign && !$has_id) {
      $canonical_uri = $repository->getCommitURI($commit_identifier);
      return id(new AphrontRedirectResponse())
        ->setURI($canonical_uri);
    }

    if ($request->getStr('diff')) {
      return $this->buildRawDiffResponse($drequest);
    }

    $commits = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withRepository($repository)
      ->withIdentifiers(array($commit_identifier))
      ->needCommitData(true)
      ->needAuditRequests(true)
      ->needAuditAuthority(array($viewer))
      ->setLimit(100)
      ->needIdentities(true)
      ->execute();

    $multiple_results = count($commits) > 1;

    $crumbs = $this->buildCrumbs(array(
      'commit' => !$multiple_results,
    ));
    $crumbs->setBorder(true);

    if (!$commits) {
      if (!$this->getCommitExists()) {
        return new Aphront404Response();
      }

      $error = id(new PHUIInfoView())
        ->setTitle(pht('Commit Still Parsing'))
        ->appendChild(
          pht(
            'Failed to load the commit because the commit has not been '.
            'parsed yet.'));

      $title = pht('Commit Still Parsing');

      return $this->newPage()
        ->setTitle($title)
        ->setCrumbs($crumbs)
        ->appendChild($error);
    } else if ($multiple_results) {

      $warning_message =
        pht(
          'The identifier %s is ambiguous and matches more than one commit.',
          phutil_tag(
            'strong',
            array(),
            $commit_identifier));

      $error = id(new PHUIInfoView())
        ->setTitle(pht('Ambiguous Commit'))
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->appendChild($warning_message);

      $list = id(new DiffusionCommitGraphView())
        ->setViewer($viewer)
        ->setCommits($commits);

      $crumbs->addTextCrumb(pht('Ambiguous Commit'));

      $matched_commits = id(new PHUITwoColumnView())
        ->setFooter(array(
          $error,
          $list,
        ));

      return $this->newPage()
        ->setTitle(pht('Ambiguous Commit'))
        ->setCrumbs($crumbs)
        ->appendChild($matched_commits);
    } else {
      $commit = head($commits);
    }

    $audit_requests = $commit->getAudits();

    $commit_data = $commit->getCommitData();
    $is_foreign = $commit_data->getCommitDetail('foreign-svn-stub');
    $error_panel = null;
    $unpublished_panel = null;

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

    $count = count($changes);

    $is_unreadable = false;
    $hint = null;
    if (!$count || $commit->isUnreachable()) {
      $hint = id(new DiffusionCommitHintQuery())
        ->setViewer($viewer)
        ->withRepositoryPHIDs(array($repository->getPHID()))
        ->withOldCommitIdentifiers(array($commit->getCommitIdentifier()))
        ->executeOne();
      if ($hint) {
        $is_unreadable = $hint->isUnreadable();
      }
    }

    if ($is_foreign) {
      $subpath = $commit_data->getCommitDetail('svn-subpath');

      $error_panel = new PHUIInfoView();
      $error_panel->setTitle(pht('Commit Not Tracked'));
      $error_panel->setSeverity(PHUIInfoView::SEVERITY_WARNING);
      $error_panel->appendChild(
        pht(
          "This Diffusion repository is configured to track only one ".
          "subdirectory of the entire Subversion repository, and this commit ".
          "didn't affect the tracked subdirectory ('%s'), so no ".
          "information is available.",
          $subpath));
    } else {
      $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();
      $engine->setConfig('viewer', $viewer);

      $commit_tag = $this->renderCommitHashTag($drequest);
      $header = id(new PHUIHeaderView())
        ->setHeader(nonempty($commit->getSummary(), pht('Commit Detail')))
        ->setHeaderIcon('fa-code-fork')
        ->addTag($commit_tag);

      if (!$commit->isAuditStatusNoAudit()) {
        $status = $commit->getAuditStatusObject();

        $icon = $status->getIcon();
        $color = $status->getColor();
        $status = $status->getName();

        $header->setStatus($icon, $color, $status);
      }

      $curtain = $this->buildCurtain($commit, $repository);
      $details = $this->buildPropertyListView(
        $commit,
        $commit_data,
        $audit_requests);

      $message = $commit_data->getCommitMessage();

      $revision = $commit->getCommitIdentifier();
      $message = $this->linkBugtraq($message);
      $message = $engine->markupText($message);

      $detail_list = new PHUIPropertyListView();
      $detail_list->addTextContent(
        phutil_tag(
          'div',
          array(
            'class' => 'diffusion-commit-message phabricator-remarkup',
          ),
          $message));

      if ($commit->isUnreachable()) {
        $did_rewrite = false;
        if ($hint) {
          if ($hint->isRewritten()) {
            $rewritten = id(new DiffusionCommitQuery())
              ->setViewer($viewer)
              ->withRepository($repository)
              ->withIdentifiers(array($hint->getNewCommitIdentifier()))
              ->executeOne();
            if ($rewritten) {
              $did_rewrite = true;
              $rewritten_uri = $rewritten->getURI();
              $rewritten_name = $rewritten->getLocalName();

              $rewritten_link = phutil_tag(
                'a',
                array(
                  'href' => $rewritten_uri,
                ),
                $rewritten_name);

              $this->commitErrors[] = pht(
                'This commit was rewritten after it was published, which '.
                'changed the commit hash. This old version of the commit is '.
                'no longer reachable from any branch, tag or ref. The new '.
                'version of this commit is %s.',
                $rewritten_link);
            }
          }
        }

        if (!$did_rewrite) {
          $this->commitErrors[] = pht(
            'This commit has been deleted in the repository: it is no longer '.
            'reachable from any branch, tag, or ref.');
        }
      }
      if (!$commit->isPermanentCommit()) {
        $nonpermanent_tag = id(new PHUITagView())
          ->setType(PHUITagView::TYPE_SHADE)
          ->setName(pht('Unpublished'))
          ->setColor(PHUITagView::COLOR_ORANGE);

        $header->addTag($nonpermanent_tag);

        $holds = $commit_data->newPublisherHoldReasons();

        $reasons = array();
        foreach ($holds as $hold) {
          $reasons[] = array(
            phutil_tag('strong', array(), pht('%s:', $hold->getName())),
            ' ',
            $hold->getSummary(),
          );
        }

        if (!$holds) {
          $reasons[] = pht('No further details are available.');
        }

        $doc_href = PhabricatorEnv::getDoclink(
          'Diffusion User Guide: Permanent Refs');
        $doc_link = phutil_tag(
          'a',
          array(
            'href' => $doc_href,
            'target' => '_blank',
          ),
          pht('Learn More'));

        $title = array(
          pht('Unpublished Commit'),
          pht(" \xC2\xB7 "),
          $doc_link,
        );

        $unpublished_panel = id(new PHUIInfoView())
          ->setTitle($title)
          ->setErrors($reasons)
          ->setSeverity(PHUIInfoView::SEVERITY_WARNING);
      }


      if ($this->getCommitErrors()) {
        $error_panel = id(new PHUIInfoView())
          ->appendChild($this->getCommitErrors())
          ->setSeverity(PHUIInfoView::SEVERITY_WARNING);
      }
    }

    $timeline = $this->buildComments($commit);
    $merge_table = $this->buildMergesTable($commit);

    $show_changesets = false;
    $info_panel = null;
    $change_list = null;
    $change_table = null;
    if ($is_unreadable) {
      $info_panel = $this->renderStatusMessage(
        pht('Unreadable Commit'),
        pht(
          'This commit has been marked as unreadable by an administrator. '.
          'It may have been corrupted or created improperly by an external '.
          'tool.'));
    } else if ($is_foreign) {
      // Don't render anything else.
    } else if (!$commit->isImported()) {
      $info_panel = $this->renderStatusMessage(
        pht('Still Importing...'),
        pht(
          'This commit is still importing. Changes will be visible once '.
          'the import finishes.'));
    } else if (!count($changes)) {
      $info_panel = $this->renderStatusMessage(
        pht('Empty Commit'),
        pht(
          'This commit is empty and does not affect any paths.'));
    } else if ($was_limited) {
      $info_panel = $this->renderStatusMessage(
        pht('Very Large Commit'),
        pht(
          'This commit is very large, and affects more than %d files. '.
          'Changes are not shown.',
          $hard_limit));
    } else if (!$this->getCommitExists()) {
      $info_panel = $this->renderStatusMessage(
        pht('Commit No Longer Exists'),
        pht('This commit no longer exists in the repository.'));
    } else {
      $show_changesets = true;

      // The user has clicked "Show All Changes", and we should show all the
      // changes inline even if there are more than the soft limit.
      $show_all_details = $request->getBool('show_all');

      $change_header = id(new PHUIHeaderView())
        ->setHeader(pht('Changes (%s)', new PhutilNumber($count)));

      $warning_view = null;
      if ($count > self::CHANGES_LIMIT && !$show_all_details) {
        $button = id(new PHUIButtonView())
          ->setText(pht('Show All Changes'))
          ->setHref('?show_all=true')
          ->setTag('a')
          ->setIcon('fa-files-o');

        $warning_view = id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
          ->setTitle(pht('Very Large Commit'))
          ->appendChild(
            pht('This commit is very large. Load each file individually.'));

        $change_header->addActionLink($button);
      }

      $changesets = DiffusionPathChange::convertToDifferentialChangesets(
        $viewer,
        $changes);

      // TODO: This table and panel shouldn't really be separate, but we need
      // to clean up the "Load All Files" interaction first.
      $change_table = $this->buildTableOfContents(
        $changesets,
        $change_header,
        $warning_view);

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
          throw new Exception(pht('Unknown VCS.'));
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

        $inlines = id(new DiffusionDiffInlineCommentQuery())
          ->setViewer($viewer)
          ->withCommitPHIDs(array($commit->getPHID()))
          ->withPublishedComments(true)
          ->withPublishableComments(true)
          ->execute();
        $inlines = mpull($inlines, 'newInlineCommentObject');

        $path_ids = mpull($inlines, null, 'getPathID');
        foreach ($changesets as $key => $changeset) {
          if (array_key_exists($changeset->getID(), $path_ids)) {
            $visible_changesets[$key] = $changeset;
          }
        }
      }

      $change_list_title = $commit->getDisplayName();

      $change_list = new DifferentialChangesetListView();
      $change_list->setTitle($change_list_title);
      $change_list->setChangesets($changesets);
      $change_list->setVisibleChangesets($visible_changesets);
      $change_list->setRenderingReferences($references);
      $change_list->setRenderURI($repository->getPathURI('diff/'));
      $change_list->setRepository($repository);
      $change_list->setUser($viewer);
      $change_list->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);

      // TODO: Try to setBranch() to something reasonable here?

      $change_list->setStandaloneURI(
        $repository->getPathURI('diff/'));

      $change_list->setRawFileURIs(
        // TODO: Implement this, somewhat tricky if there's an octopus merge
        // or whatever?
        null,
        $repository->getPathURI('diff/?view=r'));

      $change_list->setInlineCommentControllerURI(
        '/diffusion/inline/edit/'.phutil_escape_uri($commit->getPHID()).'/');

    }

    $add_comment = $this->renderAddCommentPanel(
      $commit,
      $timeline);

    $filetree = id(new DifferentialFileTreeEngine())
      ->setViewer($viewer)
      ->setDisabled(!$show_changesets);

    if ($show_changesets) {
      $filetree->setChangesets($changesets);
    }

    $description_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Description'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($detail_list);

    $detail_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($details);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $unpublished_panel,
          $error_panel,
          $description_box,
          $detail_box,
          $timeline,
          $merge_table,
          $info_panel,
        ))
      ->setFooter(
        array(
          $change_table,
          $change_list,
          $add_comment,
        ));

    $main_content = array(
      $crumbs,
      $view,
    );

    $main_content = $filetree->newView($main_content);
    if (!$filetree->getDisabled()) {
      $change_list->setFormationView($main_content);
    }

    $page = $this->newPage()
      ->setTitle($commit->getDisplayName())
      ->setPageObjectPHIDS(array($commit->getPHID()))
      ->appendChild($main_content);

    return $page;

  }

  private function buildPropertyListView(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data,
    array $audit_requests) {

    $viewer = $this->getViewer();
    $commit_phid = $commit->getPHID();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $view = id(new PHUIPropertyListView())
      ->setUser($this->getRequest()->getUser())
      ->setObject($commit);

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


    if ($data->getCommitDetail('reviewerPHID')) {
      $phids[] = $data->getCommitDetail('reviewerPHID');
    }

    $phids[] = $commit->getCommitterDisplayPHID();
    $phids[] = $commit->getAuthorDisplayPHID();

    // NOTE: We should never normally have more than a single push log, but
    // it can occur naturally if a commit is pushed, then the branch it was
    // on is deleted, then the commit is pushed again (or through other similar
    // chains of events). This should be rare, but does not indicate a bug
    // or data issue.

    // NOTE: We never query push logs in SVN because the committer is always
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
        $view->addProperty(
          pht('Auditors'),
          $this->renderAuditStatusView($commit, $user_requests));
      }

      if ($other_requests) {
        $view->addProperty(
          pht('Group Auditors'),
          $this->renderAuditStatusView($commit, $other_requests));
      }
    }

    $provenance_list = new PHUIStatusListView();

    $author_view = $commit->newCommitAuthorView($viewer);
    if ($author_view) {
      $author_date = $data->getAuthorEpoch();
      $author_date = phabricator_datetime($author_date, $viewer);

      $provenance_list->addItem(
        id(new PHUIStatusItemView())
          ->setTarget($author_view)
          ->setNote(pht('Authored on %s', $author_date)));
    }

    if (!$commit->isAuthorSameAsCommitter()) {
      $committer_view = $commit->newCommitCommitterView($viewer);
      if ($committer_view) {
        $committer_date = $commit->getEpoch();
        $committer_date = phabricator_datetime($committer_date, $viewer);

        $provenance_list->addItem(
          id(new PHUIStatusItemView())
            ->setTarget($committer_view)
            ->setNote(pht('Committed on %s', $committer_date)));
      }
    }

    if ($push_logs) {
      $pushed_list = new PHUIStatusListView();

      foreach ($push_logs as $push_log) {
        $pusher_date = $push_log->getEpoch();
        $pusher_date = phabricator_datetime($pusher_date, $viewer);

        $pusher_view = $handles[$push_log->getPusherPHID()]->renderLink();

        $provenance_list->addItem(
          id(new PHUIStatusItemView())
            ->setTarget($pusher_view)
            ->setNote(pht('Pushed on %s', $pusher_date)));
      }
    }

    $view->addProperty(pht('Provenance'), $provenance_list);

    $reviewer_phid = $data->getCommitDetail('reviewerPHID');
    if ($reviewer_phid) {
      $view->addProperty(
        pht('Reviewer'),
        $handles[$reviewer_phid]->renderLink());
    }

    if ($revision_phid) {
      $view->addProperty(
        pht('Differential Revision'),
        $handles[$revision_phid]->renderLink());
    }

    $parents = $this->getCommitParents();
    if ($parents) {
      $view->addProperty(
        pht('Parents'),
        $viewer->renderHandleList(mpull($parents, 'getPHID')));
    }

    if ($this->getCommitExists()) {
      $view->addProperty(
        pht('Branches'),
        phutil_tag(
        'span',
        array(
          'id' => 'commit-branches',
        ),
        pht('Unknown')));

      $view->addProperty(
        pht('Tags'),
        phutil_tag(
        'span',
        array(
          'id' => 'commit-tags',
        ),
        pht('Unknown')));

      $identifier = $commit->getCommitIdentifier();
      $root = $repository->getPathURI("commit/{$identifier}");
      Javelin::initBehavior(
        'diffusion-commit-branches',
        array(
          $root.'/branches/' => 'commit-branches',
          $root.'/tags/' => 'commit-tags',
        ));
    }

    $refs = $this->getCommitRefs();
    if ($refs) {
      $ref_links = array();
      foreach ($refs as $ref_data) {
        $ref_links[] = phutil_tag(
          'a',
          array(
            'href' => $ref_data['href'],
          ),
          $ref_data['ref']);
      }
      $view->addProperty(
        pht('References'),
        phutil_implode_html(', ', $ref_links));
    }

    if ($reverts_phids) {
      $view->addProperty(
        pht('Reverts'),
        $viewer->renderHandleList($reverts_phids));
    }

    if ($reverted_by_phids) {
      $view->addProperty(
        pht('Reverted By'),
        $viewer->renderHandleList($reverted_by_phids));
    }

    if ($task_phids) {
      $task_list = array();
      foreach ($task_phids as $phid) {
        $task_list[] = $handles[$phid]->renderLink();
      }
      $task_list = phutil_implode_html(phutil_tag('br'), $task_list);
      $view->addProperty(
        pht('Tasks'),
        $task_list);
    }

    return $view;
  }

  private function buildComments(PhabricatorRepositoryCommit $commit) {
    $timeline = $this->buildTransactionTimeline(
      $commit,
      new PhabricatorAuditTransactionQuery());

    $timeline->setQuoteRef($commit->getMonogram());

    return $timeline;
  }

  private function renderAddCommentPanel(
    PhabricatorRepositoryCommit $commit,
    $timeline) {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    // TODO: This is pretty awkward, unify the CSS between Diffusion and
    // Differential better.
    require_celerity_resource('differential-core-view-css');

    $comment_view = id(new DiffusionCommitEditEngine())
      ->setViewer($viewer)
      ->buildEditEngineCommentView($commit);

    $comment_view->setTransactionTimeline($timeline);

    return $comment_view;
  }

  private function buildMergesTable(PhabricatorRepositoryCommit $commit) {
    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $merges = $this->getCommitMerges();
    if (!$merges) {
      return null;
    }

    $limit = $this->getMergeDisplayLimit();

    $caption = null;
    if (count($merges) > $limit) {
      $merges = array_slice($merges, 0, $limit);
      $caption = new PHUIInfoView();
      $caption->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
      $caption->appendChild(
        pht(
          'This commit merges a very large number of changes. '.
          'Only the first %s are shown.',
          new PhutilNumber($limit)));
    }

    $commit_list = id(new DiffusionCommitGraphView())
      ->setViewer($viewer)
      ->setDiffusionRequest($drequest)
      ->setHistory($merges);

    $panel = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Merged Changes'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($commit_list->newObjectItemListView());
    if ($caption) {
      $panel->setInfoView($caption);
    }

    return $panel;
  }

  private function buildCurtain(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepository $repository) {

    $request = $this->getRequest();
    $viewer = $this->getViewer();
    $curtain = $this->newCurtainView($commit);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $commit,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $commit->getID();
    $edit_uri = $this->getApplicationURI("/commit/edit/{$id}/");

    $action = id(new PhabricatorActionView())
      ->setName(pht('Edit Commit'))
      ->setHref($edit_uri)
      ->setIcon('fa-pencil')
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);
    $curtain->addAction($action);

    $action = id(new PhabricatorActionView())
      ->setName(pht('Download Raw Diff'))
      ->setHref($request->getRequestURI()->alter('diff', true))
      ->setIcon('fa-download');
    $curtain->addAction($action);

    $relationship_list = PhabricatorObjectRelationshipList::newForObject(
      $viewer,
      $commit);

    $relationship_submenu = $relationship_list->newActionMenu();
    if ($relationship_submenu) {
      $curtain->addAction($relationship_submenu);
    }

    return $curtain;
  }

  private function buildRawDiffResponse(DiffusionRequest $drequest) {
    $diff_info = $this->callConduitWithDiffusionRequest(
      'diffusion.rawdiffquery',
      array(
        'commit' => $drequest->getCommit(),
        'path' => $drequest->getPath(),
      ));

    $file_phid = $diff_info['filePHID'];

    $file = id(new PhabricatorFileQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($file_phid))
      ->executeOne();
    if (!$file) {
      throw new Exception(
        pht(
          'Failed to load file ("%s") returned by "%s".',
          $file_phid,
          'diffusion.rawdiffquery'));
    }

    return $file->getRedirectResponse();
  }

  private function renderAuditStatusView(
    PhabricatorRepositoryCommit $commit,
    array $audit_requests) {
    assert_instances_of($audit_requests, 'PhabricatorRepositoryAuditRequest');
    $viewer = $this->getViewer();

    $view = new PHUIStatusListView();
    foreach ($audit_requests as $request) {
      $status = $request->getAuditRequestStatusObject();

      $item = new PHUIStatusItemView();
      $item->setIcon(
        $status->getIconIcon(),
        $status->getIconColor(),
        $status->getStatusName());

      $auditor_phid = $request->getAuditorPHID();
      $target = $viewer->renderHandle($auditor_phid);
      $item->setTarget($target);

      if ($commit->hasAuditAuthority($viewer, $request)) {
        $item->setHighlighted(true);
      }

      $view->addItem($item);
    }

    return $view;
  }

  private function linkBugtraq($corpus) {
    $url = PhabricatorEnv::getEnvConfig('bugtraq.url');
    if ($url === null || !strlen($url)) {
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

  private function buildTableOfContents(
    array $changesets,
    $header,
    $info_view) {

    $drequest = $this->getDiffusionRequest();
    $viewer = $this->getViewer();

    $toc_view = id(new PHUIDiffTableOfContentsListView())
      ->setUser($viewer)
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);

    if ($info_view) {
      $toc_view->setInfoView($info_view);
    }

    // TODO: This is hacky, we just want access to the linkX() methods on
    // DiffusionView.
    $diffusion_view = id(new DiffusionEmptyResultView())
      ->setDiffusionRequest($drequest);

    $have_owners = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorOwnersApplication',
      $viewer);

    if (!$changesets) {
      $have_owners = false;
    }

    if ($have_owners) {
      if ($viewer->getPHID()) {
        $packages = id(new PhabricatorOwnersPackageQuery())
          ->setViewer($viewer)
          ->withStatuses(array(PhabricatorOwnersPackage::STATUS_ACTIVE))
          ->withAuthorityPHIDs(array($viewer->getPHID()))
          ->execute();
        $toc_view->setAuthorityPackages($packages);
      }

      $repository = $drequest->getRepository();
      $repository_phid = $repository->getPHID();

      $control_query = id(new PhabricatorOwnersPackageQuery())
        ->setViewer($viewer)
        ->withStatuses(array(PhabricatorOwnersPackage::STATUS_ACTIVE))
        ->withControl($repository_phid, mpull($changesets, 'getFilename'));
      $control_query->execute();
    }

    foreach ($changesets as $changeset_id => $changeset) {
      $path = $changeset->getFilename();
      $anchor = $changeset->getAnchorName();

      $history_link = $diffusion_view->linkHistory($path);
      $browse_link = $diffusion_view->linkBrowse(
        $path,
        array(
          'type' => $changeset->getFileType(),
        ));

      $item = id(new PHUIDiffTableOfContentsItemView())
        ->setChangeset($changeset)
        ->setAnchor($anchor)
        ->setContext(
          array(
            $history_link,
            ' ',
            $browse_link,
          ));

      if ($have_owners) {
        $packages = $control_query->getControllingPackagesForPath(
          $repository_phid,
          $changeset->getFilename());
        $item->setPackages($packages);
      }

      $toc_view->addItem($item);
    }

    return $toc_view;
  }

  private function loadCommitState() {
    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $commit = $drequest->getCommit();

    // TODO: We could use futures here and resolve these calls in parallel.

    $exceptions = array();

    try {
      $parent_refs = $this->callConduitWithDiffusionRequest(
        'diffusion.commitparentsquery',
        array(
          'commit' => $commit,
        ));

      if ($parent_refs) {
        $parents = id(new DiffusionCommitQuery())
          ->setViewer($viewer)
          ->withRepository($repository)
          ->withIdentifiers($parent_refs)
          ->execute();
      } else {
        $parents = array();
      }

      $this->commitParents = $parents;
    } catch (Exception $ex) {
      $this->commitParents = false;
      $exceptions[] = $ex;
    }

    $merge_limit = $this->getMergeDisplayLimit();

    try {
      if ($repository->isSVN()) {
        $this->commitMerges = array();
      } else {
        $merges = $this->callConduitWithDiffusionRequest(
          'diffusion.mergedcommitsquery',
          array(
            'commit' => $commit,
            'limit' => $merge_limit + 1,
          ));
        $this->commitMerges = DiffusionPathChange::newFromConduit($merges);
      }
    } catch (Exception $ex) {
      $this->commitMerges = false;
      $exceptions[] = $ex;
    }


    try {
      if ($repository->isGit()) {
        $refs = $this->callConduitWithDiffusionRequest(
          'diffusion.refsquery',
          array(
            'commit' => $commit,
          ));
      } else {
        $refs = array();
      }

      $this->commitRefs = $refs;
    } catch (Exception $ex) {
      $this->commitRefs = false;
      $exceptions[] = $ex;
    }

    if ($exceptions) {
      $exists = $this->callConduitWithDiffusionRequest(
        'diffusion.existsquery',
        array(
          'commit' => $commit,
        ));

      if ($exists) {
        $this->commitExists = true;
        foreach ($exceptions as $exception) {
          $this->commitErrors[] = $exception->getMessage();
        }
      } else {
        $this->commitExists = false;
        $this->commitErrors[] = pht(
          'This commit no longer exists in the repository. It may have '.
          'been part of a branch which was deleted.');
      }
    } else {
      $this->commitExists = true;
      $this->commitErrors = array();
    }
  }

  private function getMergeDisplayLimit() {
    return 50;
  }

  private function getCommitExists() {
    if ($this->commitExists === null) {
      $this->loadCommitState();
    }

    return $this->commitExists;
  }

  private function getCommitParents() {
    if ($this->commitParents === null) {
      $this->loadCommitState();
    }

    return $this->commitParents;
  }

  private function getCommitRefs() {
    if ($this->commitRefs === null) {
      $this->loadCommitState();
    }

    return $this->commitRefs;
  }

  private function getCommitMerges() {
    if ($this->commitMerges === null) {
      $this->loadCommitState();
    }

    return $this->commitMerges;
  }

  private function getCommitErrors() {
    if ($this->commitErrors === null) {
      $this->loadCommitState();
    }

    return $this->commitErrors;
  }


}
