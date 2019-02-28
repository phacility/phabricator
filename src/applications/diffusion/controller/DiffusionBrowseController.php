<?php

final class DiffusionBrowseController extends DiffusionController {

  private $lintCommit;
  private $lintMessages;
  private $corpusButtons = array();

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $drequest = $this->getDiffusionRequest();

    // Figure out if we're browsing a directory, a file, or a search result
    // list.

    $grep = $request->getStr('grep');
    if (strlen($grep)) {
      return $this->browseSearch();
    }

    $pager = id(new PHUIPagerView())
      ->readFromRequest($request);

    $results = DiffusionBrowseResultSet::newFromConduit(
      $this->callConduitWithDiffusionRequest(
        'diffusion.browsequery',
        array(
          'path' => $drequest->getPath(),
          'commit' => $drequest->getStableCommit(),
          'offset' => $pager->getOffset(),
          'limit' => $pager->getPageSize() + 1,
        )));

    $reason = $results->getReasonForEmptyResultSet();
    $is_file = ($reason == DiffusionBrowseResultSet::REASON_IS_FILE);

    if ($is_file) {
      return $this->browseFile();
    }

    $paths = $results->getPaths();
    $paths = $pager->sliceResults($paths);
    $results->setPaths($paths);

    return $this->browseDirectory($results, $pager);
  }

  private function browseSearch() {
    $drequest = $this->getDiffusionRequest();
    $header = $this->buildHeaderView($drequest);
    $path = nonempty(basename($drequest->getPath()), '/');

    $search_results = $this->renderSearchResults();
    $search_form = $this->renderSearchForm($path);

    $search_form = phutil_tag(
      'div',
      array(
        'class' => 'diffusion-mobile-search-form',
      ),
      $search_form);

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));
    $crumbs->setBorder(true);

    $tabs = $this->buildTabsView('code');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setTabs($tabs)
      ->setFooter(
        array(
          $search_form,
          $search_results,
        ));

    return $this->newPage()
      ->setTitle(
        array(
          nonempty(basename($drequest->getPath()), '/'),
          $drequest->getRepository()->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function browseFile() {
    $viewer = $this->getViewer();
    $request = $this->getRequest();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $before = $request->getStr('before');
    if ($before) {
      return $this->buildBeforeResponse($before);
    }

    $path = $drequest->getPath();
    $params = array(
      'commit' => $drequest->getCommit(),
      'path' => $drequest->getPath(),
    );

    $view = $request->getStr('view');

    $byte_limit = null;
    if ($view !== 'raw') {
      $byte_limit = PhabricatorFileStorageEngine::getChunkThreshold();
      $time_limit = 10;

      $params += array(
        'timeout' => $time_limit,
        'byteLimit' => $byte_limit,
      );
    }

    $response = $this->callConduitWithDiffusionRequest(
      'diffusion.filecontentquery',
      $params);

    $hit_byte_limit = $response['tooHuge'];
    $hit_time_limit = $response['tooSlow'];

    $file_phid = $response['filePHID'];
    $show_editor = false;
    if ($hit_byte_limit) {
      $corpus = $this->buildErrorCorpus(
        pht(
          'This file is larger than %s byte(s), and too large to display '.
          'in the web UI.',
          phutil_format_bytes($byte_limit)));
    } else if ($hit_time_limit) {
      $corpus = $this->buildErrorCorpus(
        pht(
          'This file took too long to load from the repository (more than '.
          '%s second(s)).',
          new PhutilNumber($time_limit)));
    } else {
      $file = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($file_phid))
        ->executeOne();
      if (!$file) {
        throw new Exception(pht('Failed to load content file!'));
      }

      if ($view === 'raw') {
        return $file->getRedirectResponse();
      }

      $data = $file->loadFileData();

      $lfs_ref = $this->getGitLFSRef($repository, $data);
      if ($lfs_ref) {
        if ($view == 'git-lfs') {
          $file = $this->loadGitLFSFile($lfs_ref);

          // Rename the file locally so we generate a better vanity URI for
          // it. In storage, it just has a name like "lfs-13f9a94c0923...",
          // since we don't get any hints about possible human-readable names
          // at upload time.
          $basename = basename($drequest->getPath());
          $file->makeEphemeral();
          $file->setName($basename);

          return $file->getRedirectResponse();
        }

        $corpus = $this->buildGitLFSCorpus($lfs_ref);
      } else {
        $show_editor = true;

        $ref = id(new PhabricatorDocumentRef())
          ->setFile($file);

        $engine = id(new DiffusionDocumentRenderingEngine())
          ->setRequest($request)
          ->setDiffusionRequest($drequest);

        $corpus = $engine->newDocumentView($ref);

        $this->corpusButtons[] = $this->renderFileButton();
      }
    }

    $bar = $this->buildButtonBar($drequest, $show_editor);
    $header = $this->buildHeaderView($drequest);
    $header->setHeaderIcon('fa-file-code-o');

    $follow  = $request->getStr('follow');
    $follow_notice = null;
    if ($follow) {
      $follow_notice = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setTitle(pht('Unable to Continue'));
      switch ($follow) {
        case 'first':
          $follow_notice->appendChild(
            pht(
              'Unable to continue tracing the history of this file because '.
              'this commit is the first commit in the repository.'));
          break;
        case 'created':
          $follow_notice->appendChild(
            pht(
              'Unable to continue tracing the history of this file because '.
              'this commit created the file.'));
          break;
      }
    }

    $renamed = $request->getStr('renamed');
    $renamed_notice = null;
    if ($renamed) {
      $renamed_notice = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->setTitle(pht('File Renamed'))
        ->appendChild(
          pht(
            'File history passes through a rename from "%s" to "%s".',
            $drequest->getPath(),
            $renamed));
    }

    $open_revisions = $this->buildOpenRevisions();
    $owners_list = $this->buildOwnersList($drequest);

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));
    $crumbs->setBorder(true);

    $basename = basename($this->getDiffusionRequest()->getPath());
    $tabs = $this->buildTabsView('code');
    $bar->setRight($this->corpusButtons);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setTabs($tabs)
      ->setFooter(array(
        $bar,
        $follow_notice,
        $renamed_notice,
        $corpus,
        $open_revisions,
        $owners_list,
      ));

    $title = array($basename, $repository->getDisplayName());

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));

  }

  public function browseDirectory(
    DiffusionBrowseResultSet $results,
    PHUIPagerView $pager) {

    $request = $this->getRequest();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $reason = $results->getReasonForEmptyResultSet();

    $this->buildActionButtons($drequest, true);
    $details = $this->buildPropertyView($drequest);

    $header = $this->buildHeaderView($drequest);
    $header->setHeaderIcon('fa-folder-open');

    $empty_result = null;
    $browse_panel = null;
    $branch_panel = null;
    if (!$results->isValidResults()) {
      $empty_result = new DiffusionEmptyResultView();
      $empty_result->setDiffusionRequest($drequest);
      $empty_result->setDiffusionBrowseResultSet($results);
      $empty_result->setView($request->getStr('view'));
    } else {
      $phids = array();
      foreach ($results->getPaths() as $result) {
        $data = $result->getLastCommitData();
        if ($data) {
          if ($data->getCommitDetail('authorPHID')) {
            $phids[$data->getCommitDetail('authorPHID')] = true;
          }
        }
      }

      $phids = array_keys($phids);
      $handles = $this->loadViewerHandles($phids);

      $browse_table = id(new DiffusionBrowseTableView())
        ->setDiffusionRequest($drequest)
        ->setHandles($handles)
        ->setPaths($results->getPaths())
        ->setUser($request->getUser());

      $title = nonempty(basename($drequest->getPath()), '/');
      $icon = 'fa-folder-open';
      $browse_header = $this->buildPanelHeaderView($title, $icon);

      $browse_panel = id(new PHUIObjectBoxView())
        ->setHeader($browse_header)
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->setTable($browse_table)
        ->addClass('diffusion-mobile-view')
        ->setPager($pager);

      $path = $drequest->getPath();
      $is_branch = (!strlen($path) && $repository->supportsBranchComparison());
      if ($is_branch) {
        $branch_panel = $this->buildBranchTable();
      }
    }

    $open_revisions = $this->buildOpenRevisions();
    $readme = $this->renderDirectoryReadme($results);

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));

    $crumbs->setBorder(true);
    $tabs = $this->buildTabsView('code');
    $owners_list = $this->buildOwnersList($drequest);
    $bar = id(new PHUILeftRightView())
      ->setRight($this->corpusButtons)
      ->addClass('diffusion-action-bar');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setTabs($tabs)
      ->setFooter(
        array(
          $bar,
          $branch_panel,
          $empty_result,
          $browse_panel,
          $open_revisions,
          $owners_list,
          $readme,
        ));

    if ($details) {
      $view->addPropertySection(pht('Details'), $details);
    }

    return $this->newPage()
      ->setTitle(array(
          nonempty(basename($drequest->getPath()), '/'),
          $repository->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
        ));
  }

  private function renderSearchResults() {
    $request = $this->getRequest();

    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $results = array();

    $pager = id(new PHUIPagerView())
      ->readFromRequest($request);

    $search_mode = null;
    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $results = array();
        break;
      default:
        if (strlen($this->getRequest()->getStr('grep'))) {
          $search_mode = 'grep';
          $query_string = $request->getStr('grep');
          $results = $this->callConduitWithDiffusionRequest(
            'diffusion.searchquery',
            array(
              'grep' => $query_string,
              'commit' => $drequest->getStableCommit(),
              'path' => $drequest->getPath(),
              'limit' => $pager->getPageSize() + 1,
              'offset' => $pager->getOffset(),
            ));
        }
        break;
    }
    $results = $pager->sliceResults($results);

    $table = null;
    $header = null;
    if ($search_mode == 'grep') {
      $table = $this->renderGrepResults($results, $query_string);
      $title = pht(
        'File content matching "%s" under "%s"',
        $query_string,
        nonempty($drequest->getPath(), '/'));
      $header = id(new PHUIHeaderView())
        ->setHeader($title)
        ->addClass('diffusion-search-result-header');
    }

    return array($header, $table, $pager);

  }

  private function renderGrepResults(array $results, $pattern) {
    $drequest = $this->getDiffusionRequest();
    require_celerity_resource('phabricator-search-results-css');

    if (!$results) {
      return id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->appendChild(
          pht(
            'The pattern you searched for was not found in the content of any '.
            'files.'));
    }

    $grouped = array();
    foreach ($results as $file) {
      list($path, $line, $string) = $file;
      $grouped[$path][] = array($line, $string);
    }

    $view = array();
    foreach ($grouped as $path => $matches) {
      $view[] = id(new DiffusionPatternSearchView())
        ->setPath($path)
        ->setMatches($matches)
        ->setPattern($pattern)
        ->setDiffusionRequest($drequest)
        ->render();
    }

    return $view;
  }

  private function buildButtonBar(
    DiffusionRequest $drequest,
    $show_editor) {

    $viewer = $this->getViewer();
    $base_uri = $this->getRequest()->getRequestURI();

    $user = $this->getRequest()->getUser();
    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $line = nonempty((int)$drequest->getLine(), 1);
    $buttons = array();

    $editor_link = $user->loadEditorLink($path, $line, $repository);
    $template = $user->loadEditorLink($path, '%l', $repository);

    $buttons[] =
      id(new PHUIButtonView())
        ->setTag('a')
        ->setText(pht('Last Change'))
        ->setColor(PHUIButtonView::GREY)
        ->setHref(
          $drequest->generateURI(
            array(
              'action' => 'change',
            )))
        ->setIcon('fa-backward');

    if ($editor_link) {
      $buttons[] =
        id(new PHUIButtonView())
          ->setTag('a')
          ->setText(pht('Open File'))
          ->setHref($editor_link)
          ->setIcon('fa-pencil')
          ->setID('editor_link')
          ->setMetadata(array('link_template' => $template))
          ->setDisabled(!$editor_link)
          ->setColor(PHUIButtonView::GREY);
    }

    $bar = id(new PHUILeftRightView())
      ->setLeft($buttons)
      ->addClass('diffusion-action-bar full-mobile-buttons');
    return $bar;
  }

  private function buildOwnersList(DiffusionRequest $drequest) {
    $viewer = $this->getViewer();

    $have_owners = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorOwnersApplication',
      $viewer);
    if (!$have_owners) {
      return null;
    }

    $repository = $drequest->getRepository();

    $package_query = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($viewer)
      ->withStatuses(array(PhabricatorOwnersPackage::STATUS_ACTIVE))
      ->withControl(
        $repository->getPHID(),
        array(
          $drequest->getPath(),
        ));

    $package_query->execute();

    $packages = $package_query->getControllingPackagesForPath(
      $repository->getPHID(),
      $drequest->getPath());

    $ownership = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setNoDataString(pht('No Owners'));

    if ($packages) {
      foreach ($packages as $package) {
        $item = id(new PHUIObjectItemView())
          ->setObject($package)
          ->setObjectName($package->getMonogram())
          ->setHeader($package->getName())
          ->setHref($package->getURI());

        $owners = $package->getOwners();
        if ($owners) {
          $owner_list = $viewer->renderHandleList(
            mpull($owners, 'getUserPHID'));
        } else {
          $owner_list = phutil_tag('em', array(), pht('None'));
        }
        $item->addAttribute(pht('Owners: %s', $owner_list));

        $auto = $package->getAutoReview();
        $autoreview_map = PhabricatorOwnersPackage::getAutoreviewOptionsMap();
        $spec = idx($autoreview_map, $auto, array());
        $name = idx($spec, 'name', $auto);
        $item->addIcon('fa-code', $name);

        $rule = $package->newAuditingRule();
        $item->addIcon($rule->getIconIcon(), $rule->getDisplayName());

        if ($package->isArchived()) {
          $item->setDisabled(true);
        }

        $ownership->addItem($item);
      }
    }

    $view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Owner Packages'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addClass('diffusion-mobile-view')
      ->setObjectList($ownership);

    return $view;
  }

  private function renderFileButton($file_uri = null, $label = null) {

    $base_uri = $this->getRequest()->getRequestURI();

    if ($file_uri) {
      $text = pht('Download File');
      $href = $file_uri;
      $icon = 'fa-download';
    } else {
      $text = pht('Raw File');
      $href = $base_uri->alter('view', 'raw');
      $icon = 'fa-file-text';
    }

    if ($label !== null) {
      $text = $label;
    }

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText($text)
      ->setHref($href)
      ->setIcon($icon)
      ->setColor(PHUIButtonView::GREY);

    return $button;
  }

  private function renderGitLFSButton() {
    $viewer = $this->getViewer();

    $uri = $this->getRequest()->getRequestURI();
    $href = $uri->alter('view', 'git-lfs');

    $text = pht('Download from Git LFS');
    $icon = 'fa-download';

    return id(new PHUIButtonView())
      ->setTag('a')
      ->setText($text)
      ->setHref($href)
      ->setIcon($icon)
      ->setColor(PHUIButtonView::GREY);
  }

  private function buildErrorCorpus($message) {
    $text = id(new PHUIBoxView())
      ->addPadding(PHUI::PADDING_LARGE)
      ->appendChild($message);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Details'));

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($text);

    return $box;
  }

  private function buildBeforeResponse($before) {
    $request = $this->getRequest();
    $drequest = $this->getDiffusionRequest();

    // NOTE: We need to get the grandparent so we can capture filename changes
    // in the parent.

    $parent = $this->loadParentCommitOf($before);
    $old_filename = null;
    $was_created = false;
    if ($parent) {
      $grandparent = $this->loadParentCommitOf($parent);

      if ($grandparent) {
        $rename_query = new DiffusionRenameHistoryQuery();
        $rename_query->setRequest($drequest);
        $rename_query->setOldCommit($grandparent);
        $rename_query->setViewer($request->getUser());
        $old_filename = $rename_query->loadOldFilename();
        $was_created = $rename_query->getWasCreated();
      }
    }

    $follow = null;
    if ($was_created) {
      // If the file was created in history, that means older commits won't
      // have it. Since we know it existed at 'before', it must have been
      // created then; jump there.
      $target_commit = $before;
      $follow = 'created';
    } else if ($parent) {
      // If we found a parent, jump to it. This is the normal case.
      $target_commit = $parent;
    } else {
      // If there's no parent, this was probably created in the initial commit?
      // And the "was_created" check will fail because we can't identify the
      // grandparent. Keep the user at 'before'.
      $target_commit = $before;
      $follow = 'first';
    }

    $path = $drequest->getPath();
    $renamed = null;
    if ($old_filename !== null &&
        $old_filename !== '/'.$path) {
      $renamed = $path;
      $path = $old_filename;
    }

    $line = null;
    // If there's a follow error, drop the line so the user sees the message.
    if (!$follow) {
      $line = $this->getBeforeLineNumber($target_commit);
    }

    $before_uri = $drequest->generateURI(
      array(
        'action'    => 'browse',
        'commit'    => $target_commit,
        'line'      => $line,
        'path'      => $path,
      ));

    if ($renamed === null) {
      $before_uri->removeQueryParam('renamed');
    } else {
      $before_uri->replaceQueryParam('renamed', $renamed);
    }

    if ($follow === null) {
      $before_uri->removeQueryParam('follow');
    } else {
      $before_uri->replaceQueryParam('follow', $follow);
    }

    return id(new AphrontRedirectResponse())->setURI($before_uri);
  }

  private function getBeforeLineNumber($target_commit) {
    $drequest = $this->getDiffusionRequest();
    $viewer = $this->getViewer();

    $line = $drequest->getLine();
    if (!$line) {
      return null;
    }

    $diff_info = $this->callConduitWithDiffusionRequest(
      'diffusion.rawdiffquery',
      array(
        'commit' => $drequest->getCommit(),
        'path' => $drequest->getPath(),
        'againstCommit' => $target_commit,
      ));

    $file_phid = $diff_info['filePHID'];
    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($file_phid))
      ->executeOne();
    if (!$file) {
      throw new Exception(
        pht(
          'Failed to load file ("%s") returned by "%s".',
          $file_phid,
          'diffusion.rawdiffquery.'));
    }

    $raw_diff = $file->loadFileData();

    $old_line = 0;
    $new_line = 0;

    foreach (explode("\n", $raw_diff) as $text) {
      if ($text[0] == '-' || $text[0] == ' ') {
        $old_line++;
      }
      if ($text[0] == '+' || $text[0] == ' ') {
        $new_line++;
      }
      if ($new_line == $line) {
        return $old_line;
      }
    }

    // We didn't find the target line.
    return $line;
  }

  private function loadParentCommitOf($commit) {
    $drequest = $this->getDiffusionRequest();
    $user = $this->getRequest()->getUser();

    $before_req = DiffusionRequest::newFromDictionary(
      array(
        'user' => $user,
        'repository' => $drequest->getRepository(),
        'commit' => $commit,
      ));

    $parents = DiffusionQuery::callConduitWithDiffusionRequest(
      $user,
      $before_req,
      'diffusion.commitparentsquery',
      array(
        'commit' => $commit,
      ));

    return head($parents);
  }

  protected function markupText($text) {
    $engine = PhabricatorMarkupEngine::newDiffusionMarkupEngine();
    $engine->setConfig('viewer', $this->getRequest()->getUser());
    $text = $engine->markupText($text);

    $text = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $text);

    return $text;
  }

  protected function buildHeaderView(DiffusionRequest $drequest) {
    $viewer = $this->getViewer();
    $repository = $drequest->getRepository();

    $commit_tag = $this->renderCommitHashTag($drequest);

    $path = nonempty($drequest->getPath(), '/');

    $search = $this->renderSearchForm($path);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($this->renderPathLinks($drequest, $mode = 'browse'))
      ->addActionItem($search)
      ->addTag($commit_tag)
      ->addClass('diffusion-browse-header');

    if (!$repository->isSVN()) {
      $branch_tag = $this->renderBranchTag($drequest);
      $header->addTag($branch_tag);
    }

    return $header;
  }

  protected function buildPanelHeaderView($title, $icon) {

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon($icon)
      ->addClass('diffusion-panel-header-view');

    return $header;

  }

  protected function buildActionButtons(
    DiffusionRequest $drequest,
    $is_directory = false) {

    $viewer = $this->getViewer();
    $repository = $drequest->getRepository();
    $history_uri = $drequest->generateURI(array('action' => 'history'));
    $behind_head = $drequest->getSymbolicCommit();
    $compare = null;
    $head_uri = $drequest->generateURI(
      array(
        'commit' => '',
        'action' => 'browse',
      ));

    if ($repository->supportsBranchComparison() && $is_directory) {
      $compare_uri = $drequest->generateURI(array('action' => 'compare'));
      $compare = id(new PHUIButtonView())
        ->setText(pht('Compare'))
        ->setIcon('fa-code-fork')
        ->setWorkflow(true)
        ->setTag('a')
        ->setHref($compare_uri)
        ->setColor(PHUIButtonView::GREY);
      $this->corpusButtons[] = $compare;
    }

    $head = null;
    if ($behind_head) {
      $head = id(new PHUIButtonView())
        ->setTag('a')
        ->setText(pht('Back to HEAD'))
        ->setHref($head_uri)
        ->setIcon('fa-home')
        ->setColor(PHUIButtonView::GREY);
      $this->corpusButtons[] = $head;
    }

    $history = id(new PHUIButtonView())
      ->setText(pht('History'))
      ->setHref($history_uri)
      ->setTag('a')
      ->setIcon('fa-history')
      ->setColor(PHUIButtonView::GREY);
    $this->corpusButtons[] = $history;

  }

  protected function buildPropertyView(
    DiffusionRequest $drequest) {

    $viewer = $this->getViewer();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    if ($drequest->getSymbolicType() == 'tag') {
      $symbolic = $drequest->getSymbolicCommit();
      $view->addProperty(pht('Tag'), $symbolic);

      $tags = $this->callConduitWithDiffusionRequest(
        'diffusion.tagsquery',
        array(
          'names' => array($symbolic),
          'needMessages' => true,
        ));
      $tags = DiffusionRepositoryTag::newFromConduit($tags);

      $tags = mpull($tags, null, 'getName');
      $tag = idx($tags, $symbolic);

      if ($tag && strlen($tag->getMessage())) {
        $view->addSectionHeader(
          pht('Tag Content'), 'fa-tag');
        $view->addTextContent($this->markupText($tag->getMessage()));
      }
    }

    if ($view->hasAnyProperties()) {
      return $view;
    }

    return null;
  }

  private function buildOpenRevisions() {
    $viewer = $this->getViewer();

    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $path = $drequest->getPath();

    $path_map = id(new DiffusionPathIDQuery(array($path)))->loadPathIDs();
    $path_id = idx($path_map, $path);
    if (!$path_id) {
      return null;
    }

    $recent = (PhabricatorTime::getNow() - phutil_units('30 days in seconds'));

    $revisions = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withPath($repository->getID(), $path_id)
      ->withIsOpen(true)
      ->withUpdatedEpochBetween($recent, null)
      ->setOrder(DifferentialRevisionQuery::ORDER_MODIFIED)
      ->setLimit(10)
      ->needReviewers(true)
      ->needFlags(true)
      ->needDrafts(true)
      ->execute();

    if (!$revisions) {
      return null;
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Recently Open Revisions'));

    $list = id(new DifferentialRevisionListView())
      ->setViewer($viewer)
      ->setRevisions($revisions)
      ->setNoBox(true);

    $view = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addClass('diffusion-mobile-view')
      ->appendChild($list);

    return $view;
  }

  private function getGitLFSRef(PhabricatorRepository $repository, $data) {
    if (!$repository->canUseGitLFS()) {
      return null;
    }

    $lfs_pattern = '(^version https://git-lfs\\.github\\.com/spec/v1[\r\n])';
    if (!preg_match($lfs_pattern, $data)) {
      return null;
    }

    $matches = null;
    if (!preg_match('(^oid sha256:(.*)$)m', $data, $matches)) {
      return null;
    }

    $hash = $matches[1];
    $hash = trim($hash);

    return id(new PhabricatorRepositoryGitLFSRefQuery())
      ->setViewer($this->getViewer())
      ->withRepositoryPHIDs(array($repository->getPHID()))
      ->withObjectHashes(array($hash))
      ->executeOne();
  }

  private function buildGitLFSCorpus(PhabricatorRepositoryGitLFSRef $ref) {
    // TODO: We should probably test if we can load the file PHID here and
    // show the user an error if we can't, rather than making them click
    // through to hit an error.

    $title = basename($this->getDiffusionRequest()->getPath());
    $icon = 'fa-archive';
    $drequest = $this->getDiffusionRequest();
    $this->buildActionButtons($drequest);
    $header = $this->buildPanelHeaderView($title, $icon);

    $severity = PHUIInfoView::SEVERITY_NOTICE;

    $messages = array();
    $messages[] = pht(
      'This %s file is stored in Git Large File Storage.',
      phutil_format_bytes($ref->getByteSize()));

    try {
      $file = $this->loadGitLFSFile($ref);
      $this->corpusButtons[] = $this->renderGitLFSButton();
    } catch (Exception $ex) {
      $severity = PHUIInfoView::SEVERITY_ERROR;
      $messages[] = pht('The data for this file could not be loaded.');
    }

    $this->corpusButtons[] = $this->renderFileButton(
      null, pht('View Raw LFS Pointer'));

    $corpus = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addClass('diffusion-mobile-view')
      ->setCollapsed(true);

    if ($messages) {
      $corpus->setInfoView(
        id(new PHUIInfoView())
          ->setSeverity($severity)
          ->setErrors($messages));
    }

    return $corpus;
  }

  private function loadGitLFSFile(PhabricatorRepositoryGitLFSRef $ref) {
    $viewer = $this->getViewer();

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($ref->getFilePHID()))
      ->executeOne();
    if (!$file) {
      throw new Exception(
        pht(
          'Failed to load file object for Git LFS ref "%s"!',
          $ref->getObjectHash()));
    }

    return $file;
  }

  private function buildBranchTable() {
    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $branch = $drequest->getBranch();
    $default_branch = $repository->getDefaultBranch();

    if ($branch === $default_branch) {
      return null;
    }

    $pager = id(new PHUIPagerView())
      ->setPageSize(10);

    try {
      $results = $this->callConduitWithDiffusionRequest(
        'diffusion.historyquery',
        array(
          'commit' => $branch,
          'against' => $default_branch,
          'path' => $drequest->getPath(),
          'offset' => $pager->getOffset(),
          'limit' => $pager->getPageSize() + 1,
        ));
    } catch (Exception $ex) {
      return null;
    }

    $history = DiffusionPathChange::newFromConduit($results['pathChanges']);
    $history = $pager->sliceResults($history);

    if (!$history) {
      return null;
    }

    $history_table = id(new DiffusionHistoryTableView())
      ->setViewer($viewer)
      ->setDiffusionRequest($drequest)
      ->setHistory($history);

    $history_table->loadRevisions();

    $history_table
      ->setParents($results['parents'])
      ->setFilterParents(true)
      ->setIsHead(true)
      ->setIsTail(!$pager->getHasMorePages());

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('%s vs %s', $branch, $default_branch));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addClass('diffusion-mobile-view')
      ->setTable($history_table);
  }

}
