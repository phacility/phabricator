<?php

final class DiffusionBrowseController extends DiffusionController {

  private $lintCommit;
  private $lintMessages;
  private $coverage;

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
    $find = $request->getStr('find');
    if (strlen($grep) || strlen($find)) {
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
      return $this->browseFile($results);
    } else {
      $paths = $results->getPaths();
      $paths = $pager->sliceResults($paths);
      $results->setPaths($paths);

      return $this->browseDirectory($results, $pager);
    }
  }

  private function browseSearch() {
    $drequest = $this->getDiffusionRequest();

    $actions = $this->buildActionView($drequest);
    $properties = $this->buildPropertyView($drequest, $actions);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($this->buildHeaderView($drequest))
      ->addPropertyList($properties);

    $content = array();

    $content[] = $object_box;
    $content[] = $this->renderSearchForm($collapsed = false);
    $content[] = $this->renderSearchResults();

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));

    return $this->newPage()
      ->setTitle(
        array(
          nonempty(basename($drequest->getPath()), '/'),
          $drequest->getRepository()->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild($content);
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

    $preferences = $viewer->loadPreferences();

    $show_blame = $request->getBool(
      'blame',
      $preferences->getPreference(
        PhabricatorUserPreferences::PREFERENCE_DIFFUSION_BLAME,
        false));
    $show_color = $request->getBool(
      'color',
      $preferences->getPreference(
        PhabricatorUserPreferences::PREFERENCE_DIFFUSION_COLOR,
        true));

    $view = $request->getStr('view');
    if ($request->isFormPost() && $view != 'raw' && $viewer->isLoggedIn()) {
      $preferences->setPreference(
        PhabricatorUserPreferences::PREFERENCE_DIFFUSION_BLAME,
        $show_blame);
      $preferences->setPreference(
        PhabricatorUserPreferences::PREFERENCE_DIFFUSION_COLOR,
        $show_color);
      $preferences->save();

      $uri = $request->getRequestURI()
        ->alter('blame', null)
        ->alter('color', null);

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    // We need the blame information if blame is on and we're building plain
    // text, or blame is on and this is an Ajax request. If blame is on and
    // this is a colorized request, we don't show blame at first (we ajax it
    // in afterward) so we don't need to query for it.
    $needs_blame = ($show_blame && !$show_color) ||
                   ($show_blame && $request->isAjax());

    $params = array(
      'commit' => $drequest->getCommit(),
      'path' => $drequest->getPath(),
    );

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
      if (ArcanistDiffUtils::isHeuristicBinaryFile($data)) {
        $file_uri = $file->getBestURI();

        if ($file->isViewableImage()) {
          $corpus = $this->buildImageCorpus($file_uri);
        } else {
          $corpus = $this->buildBinaryCorpus($file_uri, $data);
        }
      } else {
        $this->loadLintMessages();
        $this->coverage = $drequest->loadCoverage();

        // Build the content of the file.
        $corpus = $this->buildCorpus(
          $show_blame,
          $show_color,
          $data,
          $needs_blame,
          $drequest,
          $path,
          $data);
      }
    }

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())->setContent($corpus);
    }

    require_celerity_resource('diffusion-source-css');

    // Render the page.
    $view = $this->buildActionView($drequest);
    $action_list = $this->enrichActionView(
      $view,
      $drequest,
      $show_blame,
      $show_color);

    $properties = $this->buildPropertyView($drequest, $action_list);
    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($this->buildHeaderView($drequest))
      ->addPropertyList($properties);

    $content = array();
    $content[] = $object_box;

    $follow  = $request->getStr('follow');
    if ($follow) {
      $notice = new PHUIInfoView();
      $notice->setSeverity(PHUIInfoView::SEVERITY_WARNING);
      $notice->setTitle(pht('Unable to Continue'));
      switch ($follow) {
        case 'first':
          $notice->appendChild(
            pht(
              'Unable to continue tracing the history of this file because '.
              'this commit is the first commit in the repository.'));
          break;
        case 'created':
          $notice->appendChild(
            pht(
              'Unable to continue tracing the history of this file because '.
              'this commit created the file.'));
          break;
      }
      $content[] = $notice;
    }

    $renamed = $request->getStr('renamed');
    if ($renamed) {
      $notice = new PHUIInfoView();
      $notice->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
      $notice->setTitle(pht('File Renamed'));
      $notice->appendChild(
        pht(
          'File history passes through a rename from "%s" to "%s".',
          $drequest->getPath(),
          $renamed));
      $content[] = $notice;
    }

    $content[] = $corpus;
    $content[] = $this->buildOpenRevisions();

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));

    $basename = basename($this->getDiffusionRequest()->getPath());

    return $this->newPage()
      ->setTitle(
        array(
          $basename,
          $repository->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild($content);
  }

  public function browseDirectory(
    DiffusionBrowseResultSet $results,
    PHUIPagerView $pager) {

    $request = $this->getRequest();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $reason = $results->getReasonForEmptyResultSet();

    $content = array();
    $actions = $this->buildActionView($drequest);
    $properties = $this->buildPropertyView($drequest, $actions);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($this->buildHeaderView($drequest))
      ->addPropertyList($properties);

    $content[] = $object_box;
    $content[] = $this->renderSearchForm($collapsed = true);

    if (!$results->isValidResults()) {
      $empty_result = new DiffusionEmptyResultView();
      $empty_result->setDiffusionRequest($drequest);
      $empty_result->setDiffusionBrowseResultSet($results);
      $empty_result->setView($request->getStr('view'));
      $content[] = $empty_result;
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

      $browse_table = new DiffusionBrowseTableView();
      $browse_table->setDiffusionRequest($drequest);
      $browse_table->setHandles($handles);
      $browse_table->setPaths($results->getPaths());
      $browse_table->setUser($request->getUser());

      $browse_panel = new PHUIObjectBoxView();
      $browse_panel->setHeaderText($drequest->getPath(), '/');
      $browse_panel->setTable($browse_table);

      $content[] = $browse_panel;
    }

    $content[] = $this->buildOpenRevisions();
    $content[] = $this->renderDirectoryReadme($results);

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));

    $pager_box = $this->renderTablePagerBox($pager);

    return $this->newPage()
      ->setTitle(
        array(
          nonempty(basename($drequest->getPath()), '/'),
          $repository->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $content,
          $pager_box,
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
        } else { // Filename search.
          $search_mode = 'find';
          $query_string = $request->getStr('find');
          $results = $this->callConduitWithDiffusionRequest(
            'diffusion.querypaths',
            array(
              'pattern' => $query_string,
              'commit' => $drequest->getStableCommit(),
              'path' => $drequest->getPath(),
              'limit' => $pager->getPageSize() + 1,
              'offset' => $pager->getOffset(),
            ));
        }
        break;
    }
    $results = $pager->sliceResults($results);

    if ($search_mode == 'grep') {
      $table = $this->renderGrepResults($results, $query_string);
      $header = pht(
        'File content matching "%s" under "%s"',
        $query_string,
        nonempty($drequest->getPath(), '/'));
    } else {
      $table = $this->renderFindResults($results);
      $header = pht(
        'Paths matching "%s" under "%s"',
        $query_string,
        nonempty($drequest->getPath(), '/'));
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($header)
      ->setTable($table);

    $pager_box = $this->renderTablePagerBox($pager);

    return array($box, $pager_box);
  }

  private function renderGrepResults(array $results, $pattern) {
    $drequest = $this->getDiffusionRequest();

    require_celerity_resource('phabricator-search-results-css');

    $rows = array();
    foreach ($results as $result) {
      list($path, $line, $string) = $result;

      $href = $drequest->generateURI(array(
        'action' => 'browse',
        'path' => $path,
        'line' => $line,
      ));

      $matches = null;
      $count = @preg_match_all(
        '('.$pattern.')u',
        $string,
        $matches,
        PREG_OFFSET_CAPTURE);

      if (!$count) {
        $output = ltrim($string);
      } else {
        $output = array();
        $cursor = 0;
        $length = strlen($string);
        foreach ($matches[0] as $match) {
          $offset = $match[1];
          if ($cursor != $offset) {
            $output[] = array(
              'text' => substr($string, $cursor, $offset),
              'highlight' => false,
            );
          }
          $output[] = array(
            'text' => $match[0],
            'highlight' => true,
          );
          $cursor = $offset + strlen($match[0]);
        }
        if ($cursor != $length) {
          $output[] = array(
            'text' => substr($string, $cursor),
            'highlight' => false,
          );
        }

        if ($output) {
          $output[0]['text'] =  ltrim($output[0]['text']);
        }

        foreach ($output as $key => $segment) {
          if ($segment['highlight']) {
            $output[$key] = phutil_tag('strong', array(), $segment['text']);
          } else {
            $output[$key] = $segment['text'];
          }
        }
      }

      $string = phutil_tag(
        'pre',
        array('class' => 'PhabricatorMonospaced phui-source-fragment'),
        $output);

      $path = Filesystem::readablePath($path, $drequest->getPath());

      $rows[] = array(
        phutil_tag('a', array('href' => $href), $path),
        $line,
        $string,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setClassName('remarkup-code')
      ->setHeaders(array(pht('Path'), pht('Line'), pht('String')))
      ->setColumnClasses(array('', 'n', 'wide'))
      ->setNoDataString(
        pht(
          'The pattern you searched for was not found in the content of any '.
          'files.'));

    return $table;
  }

  private function renderFindResults(array $results) {
    $drequest = $this->getDiffusionRequest();

    $rows = array();
    foreach ($results as $result) {
      $href = $drequest->generateURI(array(
        'action' => 'browse',
        'path' => $result,
      ));

      $readable = Filesystem::readablePath($result, $drequest->getPath());

      $rows[] = array(
        phutil_tag('a', array('href' => $href), $readable),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(array(pht('Path')))
      ->setColumnClasses(array('wide'))
      ->setNoDataString(
        pht(
          'The pattern you searched for did not match the names of any '.
          'files.'));

    return $table;
  }

  private function loadLintMessages() {
    $drequest = $this->getDiffusionRequest();
    $branch = $drequest->loadBranch();

    if (!$branch || !$branch->getLintCommit()) {
      return;
    }

    $this->lintCommit = $branch->getLintCommit();

    $conn = id(new PhabricatorRepository())->establishConnection('r');

    $where = '';
    if ($drequest->getLint()) {
      $where = qsprintf(
        $conn,
        'AND code = %s',
        $drequest->getLint());
    }

    $this->lintMessages = queryfx_all(
      $conn,
      'SELECT * FROM %T WHERE branchID = %d %Q AND path = %s',
      PhabricatorRepository::TABLE_LINTMESSAGE,
      $branch->getID(),
      $where,
      '/'.$drequest->getPath());
  }

  private function buildCorpus(
    $show_blame,
    $show_color,
    $file_corpus,
    $needs_blame,
    DiffusionRequest $drequest,
    $path,
    $data) {

    $viewer = $this->getViewer();
    $blame_timeout = 15;
    $blame_failed = false;

    $highlight_limit = DifferentialChangesetParser::HIGHLIGHT_BYTE_LIMIT;
    $blame_limit = DifferentialChangesetParser::HIGHLIGHT_BYTE_LIMIT;
    $can_highlight = (strlen($file_corpus) <= $highlight_limit);
    $can_blame = (strlen($file_corpus) <= $blame_limit);

    if ($needs_blame && $can_blame) {
      $blame = $this->loadBlame($path, $drequest->getCommit(), $blame_timeout);
      list($blame_list, $blame_commits) = $blame;
      if ($blame_list === null) {
        $blame_failed = true;
        $blame_list = array();
      }
    } else {
      $blame_list = array();
      $blame_commits = array();
    }

    if (!$show_color) {
      $corpus = $this->renderPlaintextCorpus(
        $file_corpus,
        $blame_list,
        $blame_commits,
        $show_blame);
    } else {
      if ($can_highlight) {
        require_celerity_resource('syntax-highlighting-css');

        $highlighted = PhabricatorSyntaxHighlighter::highlightWithFilename(
          $path,
          $file_corpus);
        $lines = phutil_split_lines($highlighted);
      } else {
        $lines = phutil_split_lines($file_corpus);
      }

      $rows = $this->buildDisplayRows(
        $lines,
        $blame_list,
        $blame_commits,
        $show_blame,
        $show_color);

      $corpus_table = javelin_tag(
        'table',
        array(
          'class' => 'diffusion-source remarkup-code PhabricatorMonospaced',
          'sigil' => 'phabricator-source',
        ),
        $rows);

      if ($this->getRequest()->isAjax()) {
        return $corpus_table;
      }

      $id = celerity_generate_unique_node_id();

      $repo = $drequest->getRepository();
      $symbol_repos = nonempty($repo->getSymbolSources(), array());
      $symbol_repos[] = $repo->getPHID();

      $lang = last(explode('.', $drequest->getPath()));
      $repo_languages = $repo->getSymbolLanguages();
      $repo_languages = nonempty($repo_languages, array());
      $repo_languages = array_fill_keys($repo_languages, true);

      $needs_symbols = true;
      if ($repo_languages && $symbol_repos) {
        $have_symbols = id(new DiffusionSymbolQuery())
            ->existsSymbolsInRepository($repo->getPHID());
        if (!$have_symbols) {
          $needs_symbols = false;
        }
      }

      if ($needs_symbols && $repo_languages) {
        $needs_symbols = isset($repo_languages[$lang]);
      }

      if ($needs_symbols) {
        Javelin::initBehavior(
          'repository-crossreference',
          array(
            'container' => $id,
            'lang' => $lang,
            'repositories' => $symbol_repos,
          ));
      }

      $corpus = phutil_tag(
        'div',
        array(
          'id' => $id,
        ),
        $corpus_table);

      Javelin::initBehavior('load-blame', array('id' => $id));
    }

    $edit = $this->renderEditButton();
    $file = $this->renderFileButton();
    $header = id(new PHUIHeaderView())
      ->setHeader(pht('File Contents'))
      ->addActionLink($edit)
      ->addActionLink($file);

    $corpus = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($corpus)
      ->setCollapsed(true);

    $messages = array();

    if (!$can_highlight) {
      $messages[] = pht(
        'This file is larger than %s, so syntax highlighting is disabled '.
        'by default.',
        phutil_format_bytes($highlight_limit));
    }

    if ($show_blame && !$can_blame) {
      $messages[] = pht(
        'This file is larger than %s, so blame is disabled.',
        phutil_format_bytes($blame_limit));
    }

    if ($blame_failed) {
      $messages[] = pht(
        'Failed to load blame information for this file in %s second(s).',
        new PhutilNumber($blame_timeout));
    }

    if ($messages) {
      $corpus->setInfoView(
        id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
          ->setErrors($messages));
    }

    return $corpus;
  }

  private function enrichActionView(
    PhabricatorActionListView $view,
    DiffusionRequest $drequest,
    $show_blame,
    $show_color) {

    $viewer = $this->getRequest()->getUser();
    $base_uri = $this->getRequest()->getRequestURI();

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Show Last Change'))
        ->setHref(
          $drequest->generateURI(
            array(
              'action' => 'change',
            )))
        ->setIcon('fa-backward'));

    if ($show_blame) {
      $blame_text = pht('Disable Blame');
      $blame_icon = 'fa-exclamation-circle lightgreytext';
      $blame_value = 0;
    } else {
      $blame_text = pht('Enable Blame');
      $blame_icon = 'fa-exclamation-circle';
      $blame_value = 1;
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName($blame_text)
        ->setHref($base_uri->alter('blame', $blame_value))
        ->setIcon($blame_icon)
        ->setUser($viewer)
        ->setRenderAsForm($viewer->isLoggedIn()));

    if ($show_color) {
      $highlight_text = pht('Disable Highlighting');
      $highlight_icon = 'fa-star-o grey';
      $highlight_value = 0;
    } else {
      $highlight_text = pht('Enable Highlighting');
      $highlight_icon = 'fa-star';
      $highlight_value = 1;
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName($highlight_text)
        ->setHref($base_uri->alter('color', $highlight_value))
        ->setIcon($highlight_icon)
        ->setUser($viewer)
        ->setRenderAsForm($viewer->isLoggedIn()));

    $href = null;
    if ($this->getRequest()->getStr('lint') !== null) {
      $lint_text = pht('Hide %d Lint Message(s)', count($this->lintMessages));
      $href = $base_uri->alter('lint', null);

    } else if ($this->lintCommit === null) {
      $lint_text = pht('Lint not Available');
    } else {
      $lint_text = pht(
        'Show %d Lint Message(s)',
        count($this->lintMessages));
      $href = $this->getDiffusionRequest()->generateURI(array(
        'action' => 'browse',
        'commit' => $this->lintCommit,
      ))->alter('lint', '');
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName($lint_text)
        ->setHref($href)
        ->setIcon('fa-exclamation-triangle')
        ->setDisabled(!$href));

    return $view;
  }

  private function renderEditButton() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $drequest = $this->getDiffusionRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $line = nonempty((int)$drequest->getLine(), 1);

    $editor_link = $user->loadEditorLink($path, $line, $repository);
    $template = $user->loadEditorLink($path, '%l', $repository);

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Open in Editor'))
      ->setHref($editor_link)
      ->setIcon('fa-pencil')
      ->setID('editor_link')
      ->setMetadata(array('link_template' => $template))
      ->setDisabled(!$editor_link);

    return $button;
  }

  private function renderFileButton($file_uri = null) {

    $base_uri = $this->getRequest()->getRequestURI();

    if ($file_uri) {
      $text = pht('Download Raw File');
      $href = $file_uri;
      $icon = 'fa-download';
    } else {
      $text = pht('View Raw File');
      $href = $base_uri->alter('view', 'raw');
      $icon = 'fa-file-text';
    }

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText($text)
      ->setHref($href)
      ->setIcon($icon);

    return $button;
  }


  private function buildDisplayRows(
    array $lines,
    array $blame_list,
    array $blame_commits,
    $show_color,
    $show_blame) {

    $request = $this->getRequest();
    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $revision_map = array();
    $revisions = array();
    if ($blame_commits) {
      $commit_map = mpull($blame_commits, 'getCommitIdentifier', 'getPHID');

      $revision_ids = id(new DifferentialRevision())
        ->loadIDsByCommitPHIDs(array_keys($commit_map));
      if ($revision_ids) {
        $revisions = id(new DifferentialRevisionQuery())
          ->setViewer($viewer)
          ->withIDs($revision_ids)
          ->execute();
        $revisions = mpull($revisions, null, 'getID');
      }

      foreach ($revision_ids as $commit_phid => $revision_id) {
        $revision_map[$commit_map[$commit_phid]] = $revision_id;
      }
    }

    $phids = array();
    foreach ($blame_commits as $commit) {
      $author_phid = $commit->getAuthorPHID();
      if ($author_phid === null) {
        continue;
      }
      $phids[$author_phid] = $author_phid;
    }

    foreach ($revisions as $revision) {
      $author_phid = $revision->getAuthorPHID();
      if ($author_phid === null) {
        continue;
      }
      $phids[$author_phid] = $author_phid;
    }

    $handles = $viewer->loadHandles($phids);

    $colors = array();
    if ($blame_commits) {
      $epochs = array();

      foreach ($blame_commits as $identifier => $commit) {
        $epochs[$identifier] = $commit->getEpoch();
      }

      $epoch_list = array_filter($epochs);
      $epoch_list = array_unique($epoch_list);
      $epoch_list = array_values($epoch_list);

      $epoch_min   = min($epoch_list);
      $epoch_max   = max($epoch_list);
      $epoch_range = ($epoch_max - $epoch_min) + 1;

      foreach ($blame_commits as $identifier => $commit) {
        $epoch = $epochs[$identifier];
        if (!$epoch) {
          $color = '#ffffdd'; // Warning color, missing data.
        } else {
          $color_ratio = ($epoch - $epoch_min) / $epoch_range;
          $color_value = 0xE6 * (1.0 - $color_ratio);
          $color = sprintf(
            '#%02x%02x%02x',
            $color_value,
            0xF6,
            $color_value);
        }

        $colors[$identifier] = $color;
      }
    }

    $display = array();
    $last_identifier = null;
    $last_color = null;
    foreach ($lines as $line_index => $line) {
      $color = '#f6f6f6';
      $duplicate = false;
      if (isset($blame_list[$line_index])) {
        $identifier = $blame_list[$line_index];
        if (isset($colors[$identifier])) {
          $color = $colors[$identifier];
        }

        if ($identifier === $last_identifier) {
          $duplicate = true;
        } else {
          $last_identifier = $identifier;
        }
      }

      $display[$line_index] = array(
        'data' => $line,
        'target' => false,
        'highlighted' => false,
        'color' => $color,
        'duplicate' => $duplicate,
      );
    }

    $line_arr = array();
    $line_str = $drequest->getLine();
    $ranges = explode(',', $line_str);
    foreach ($ranges as $range) {
      if (strpos($range, '-') !== false) {
        list($min, $max) = explode('-', $range, 2);
        $line_arr[] = array(
          'min' => min($min, $max),
          'max' => max($min, $max),
        );
      } else if (strlen($range)) {
        $line_arr[] = array(
          'min' => $range,
          'max' => $range,
        );
      }
    }

    // Mark the first highlighted line as the target line.
    if ($line_arr) {
      $target_line = $line_arr[0]['min'];
      if (isset($display[$target_line - 1])) {
        $display[$target_line - 1]['target'] = true;
      }
    }

    // Mark all other highlighted lines as highlighted.
    foreach ($line_arr as $range) {
      for ($ii = $range['min']; $ii <= $range['max']; $ii++) {
        if (isset($display[$ii - 1])) {
          $display[$ii - 1]['highlighted'] = true;
        }
      }
    }

    $engine = null;
    $inlines = array();
    if ($this->getRequest()->getStr('lint') !== null && $this->lintMessages) {
      $engine = new PhabricatorMarkupEngine();
      $engine->setViewer($viewer);

      foreach ($this->lintMessages as $message) {
        $inline = id(new PhabricatorAuditInlineComment())
          ->setSyntheticAuthor(
            ArcanistLintSeverity::getStringForSeverity($message['severity']).
            ' '.$message['code'].' ('.$message['name'].')')
          ->setLineNumber($message['line'])
          ->setContent($message['description']);
        $inlines[$message['line']][] = $inline;

        $engine->addObject(
          $inline,
          PhabricatorInlineCommentInterface::MARKUP_FIELD_BODY);
      }

      $engine->process();
      require_celerity_resource('differential-changeset-view-css');
    }

    $rows = $this->renderInlines(
      idx($inlines, 0, array()),
      $show_blame,
      (bool)$this->coverage,
      $engine);

    // NOTE: We're doing this manually because rendering is otherwise
    // dominated by URI generation for very large files.
    $line_base = (string)$drequest->generateURI(
      array(
        'action'  => 'browse',
        'stable'  => true,
      ));

    require_celerity_resource('aphront-tooltip-css');
    Javelin::initBehavior('phabricator-oncopy');
    Javelin::initBehavior('phabricator-tooltips');
    Javelin::initBehavior('phabricator-line-linker');

    // Render these once, since they tend to get repeated many times in large
    // blame outputs.
    $commit_links = $this->renderCommitLinks($blame_commits, $handles);
    $revision_links = $this->renderRevisionLinks($revisions, $handles);

    $skip_text = pht('Skip Past This Commit');
    foreach ($display as $line_index => $line) {
      $row = array();

      $line_number = $line_index + 1;
      $line_href = $line_base.'$'.$line_number;

      if (isset($blame_list[$line_index])) {
        $identifier = $blame_list[$line_index];
      } else {
        $identifier = null;
      }

      $revision_link = null;
      $commit_link = null;
      $before_link = null;

      $style = 'background: '.$line['color'].';';

      if ($identifier && !$line['duplicate']) {
        if (isset($commit_links[$identifier])) {
          $commit_link = $commit_links[$identifier];
        }

        if (isset($revision_map[$identifier])) {
          $revision_id = $revision_map[$identifier];
          if (isset($revision_links[$revision_id])) {
            $revision_link = $revision_links[$revision_id];
          }
        }

        $skip_href = $line_href.'?before='.$identifier.'&view=blame';
        $before_link = javelin_tag(
          'a',
          array(
            'href'  => $skip_href,
            'sigil' => 'has-tooltip',
            'meta'  => array(
              'tip'     => $skip_text,
              'align'   => 'E',
              'size'    => 300,
            ),
          ),
          "\xC2\xAB");
      }

      $row[] = phutil_tag(
        'th',
        array(
          'class' => 'diffusion-blame-link',
        ),
        $before_link);

      $object_links = array();
      $object_links[] = $commit_link;
      if ($revision_link) {
        $object_links[] = phutil_tag('span', array(), '/');
        $object_links[] = $revision_link;
      }

      $row[] = phutil_tag(
        'th',
        array(
          'class' => 'diffusion-rev-link',
        ),
        $object_links);

      $line_link = phutil_tag(
        'a',
        array(
          'href' => $line_href,
          'style' => $style,
        ),
        $line_number);

      $row[] = javelin_tag(
        'th',
        array(
          'class' => 'diffusion-line-link',
          'sigil' => 'phabricator-source-line',
          'style' => $style,
        ),
        $line_link);

      if ($line['target']) {
        Javelin::initBehavior(
          'diffusion-jump-to',
          array(
            'target' => 'scroll_target',
          ));
        $anchor_text = phutil_tag(
          'a',
          array(
            'id' => 'scroll_target',
          ),
          '');
      } else {
        $anchor_text = null;
      }

      $row[] = phutil_tag(
        'td',
        array(
        ),
        array(
          $anchor_text,

          // NOTE: See phabricator-oncopy behavior.
          "\xE2\x80\x8B",

          // TODO: [HTML] Not ideal.
          phutil_safe_html(str_replace("\t", '  ', $line['data'])),
        ));

      if ($this->coverage) {
        require_celerity_resource('differential-changeset-view-css');
        $cov_index = $line_index;

        if (isset($this->coverage[$cov_index])) {
          $cov_class = $this->coverage[$cov_index];
        } else {
          $cov_class = 'N';
        }

        $row[] = phutil_tag(
          'td',
          array(
            'class' => 'cov cov-'.$cov_class,
          ),
          '');
      }

      $rows[] = phutil_tag(
        'tr',
        array(
          'class' => ($line['highlighted'] ?
                      'phabricator-source-highlight' :
                      null),
        ),
        $row);

      $cur_inlines = $this->renderInlines(
        idx($inlines, $line_number, array()),
        $show_blame,
        $this->coverage,
        $engine);
      foreach ($cur_inlines as $cur_inline) {
        $rows[] = $cur_inline;
      }
    }

    return $rows;
  }

  private function renderInlines(
    array $inlines,
    $show_blame,
    $has_coverage,
    $engine) {

    $rows = array();
    foreach ($inlines as $inline) {

      // TODO: This should use modern scaffolding code.

      $inline_view = id(new PHUIDiffInlineCommentDetailView())
        ->setUser($this->getViewer())
        ->setMarkupEngine($engine)
        ->setInlineComment($inline)
        ->render();

      $row = array_fill(0, ($show_blame ? 3 : 1), phutil_tag('th'));

      $row[] = phutil_tag('td', array(), $inline_view);

      if ($has_coverage) {
        $row[] = phutil_tag(
          'td',
          array(
            'class' => 'cov cov-I',
          ));
      }

      $rows[] = phutil_tag('tr', array('class' => 'inline'), $row);
    }

    return $rows;
  }

  private function buildImageCorpus($file_uri) {
    $properties = new PHUIPropertyListView();

    $properties->addImageContent(
      phutil_tag(
        'img',
        array(
          'src' => $file_uri,
        )));

    $file = $this->renderFileButton($file_uri);
    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Image'))
      ->addActionLink($file);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);
  }

  private function buildBinaryCorpus($file_uri, $data) {
    $size = new PhutilNumber(strlen($data));
    $text = pht('This is a binary file. It is %s byte(s) in length.', $size);
    $text = id(new PHUIBoxView())
      ->addPadding(PHUI::PADDING_LARGE)
      ->appendChild($text);

    $file = $this->renderFileButton($file_uri);
    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Details'))
      ->addActionLink($file);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($text);

    return $box;
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

    $before_uri->setQueryParams($request->getRequestURI()->getQueryParams());
    $before_uri = $before_uri->alter('before', null);
    $before_uri = $before_uri->alter('renamed', $renamed);
    $before_uri = $before_uri->alter('follow', $follow);

    return id(new AphrontRedirectResponse())->setURI($before_uri);
  }

  private function getBeforeLineNumber($target_commit) {
    $drequest = $this->getDiffusionRequest();

    $line = $drequest->getLine();
    if (!$line) {
      return null;
    }

    $raw_diff = $this->callConduitWithDiffusionRequest(
      'diffusion.rawdiffquery',
      array(
        'commit' => $drequest->getCommit(),
        'path' => $drequest->getPath(),
        'againstCommit' => $target_commit,
      ));
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

  private function renderRevisionTooltip(
    DifferentialRevision $revision,
    $handles) {
    $viewer = $this->getRequest()->getUser();

    $date = phabricator_date($revision->getDateModified(), $viewer);
    $id = $revision->getID();
    $title = $revision->getTitle();
    $header = "D{$id} {$title}";

    $author = $handles[$revision->getAuthorPHID()]->getName();

    return "{$header}\n{$date} \xC2\xB7 {$author}";
  }

  private function renderCommitTooltip(
    PhabricatorRepositoryCommit $commit,
    $author) {

    $viewer = $this->getRequest()->getUser();

    $date = phabricator_date($commit->getEpoch(), $viewer);
    $summary = trim($commit->getSummary());

    return "{$summary}\n{$date} \xC2\xB7 {$author}";
  }

  protected function renderSearchForm($collapsed) {
    $drequest = $this->getDiffusionRequest();

    $forms = array();
    $form = id(new AphrontFormView())
      ->setUser($this->getRequest()->getUser())
      ->setMethod('GET');

    switch ($drequest->getRepository()->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $forms[] = id(clone $form)
          ->appendChild(pht('Search is not available in Subversion.'));
        break;
      default:
        $forms[] = id(clone $form)
          ->appendChild(
            id(new AphrontFormTextWithSubmitControl())
              ->setLabel(pht('File Name'))
              ->setSubmitLabel(pht('Search File Names'))
              ->setName('find')
              ->setValue($this->getRequest()->getStr('find')));
        $forms[] = id(clone $form)
          ->appendChild(
            id(new AphrontFormTextWithSubmitControl())
              ->setLabel(pht('Pattern'))
              ->setSubmitLabel(pht('Grep File Content'))
              ->setName('grep')
              ->setValue($this->getRequest()->getStr('grep')));
        break;
    }

    $filter = new AphrontListFilterView();
    $filter->appendChild($forms);

    if ($collapsed) {
      $filter->setCollapsed(
        pht('Show Search'),
        pht('Hide Search'),
        pht('Search for file names or content in this directory.'),
        '#');
    }

    $filter = id(new PHUIBoxView())
      ->addClass('mlt mlb')
      ->appendChild($filter);

    return $filter;
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
    $viewer = $this->getRequest()->getUser();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($this->renderPathLinks($drequest, $mode = 'browse'))
      ->setPolicyObject($drequest->getRepository());

    return $header;
  }

  protected function buildActionView(DiffusionRequest $drequest) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $history_uri = $drequest->generateURI(
      array(
        'action' => 'history',
      ));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View History'))
        ->setHref($history_uri)
        ->setIcon('fa-list'));

    $behind_head = $drequest->getSymbolicCommit();
    $head_uri = $drequest->generateURI(
      array(
        'commit' => '',
        'action' => 'browse',
      ));
    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Jump to HEAD'))
        ->setHref($head_uri)
        ->setIcon('fa-home')
        ->setDisabled(!$behind_head));

    return $view;
  }

  protected function buildPropertyView(
    DiffusionRequest $drequest,
    PhabricatorActionListView $actions) {

    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $stable_commit = $drequest->getStableCommit();

    $view->addProperty(
      pht('Commit'),
      phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'action' => 'commit',
              'commit' => $stable_commit,
            )),
        ),
        $drequest->getRepository()->formatCommitName($stable_commit)));

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

    $repository = $drequest->getRepository();

    $owners = 'PhabricatorOwnersApplication';
    if (PhabricatorApplication::isClassInstalled($owners)) {
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

      if ($packages) {
        $ownership = id(new PHUIStatusListView())
          ->setUser($viewer);

        foreach ($packages as $package) {
          $icon = 'fa-list-alt';
          $color = 'grey';

          $item = id(new PHUIStatusItemView())
            ->setIcon($icon, $color)
            ->setTarget($viewer->renderHandle($package->getPHID()));

          $ownership->addItem($item);
        }
      } else {
        $ownership = phutil_tag('em', array(), pht('None'));
      }

      $view->addProperty(pht('Packages'), $ownership);
    }

    return $view;
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
      ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
      ->withUpdatedEpochBetween($recent, null)
      ->setOrder(DifferentialRevisionQuery::ORDER_MODIFIED)
      ->setLimit(10)
      ->needRelationships(true)
      ->needFlags(true)
      ->needDrafts(true)
      ->execute();

    if (!$revisions) {
      return null;
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Open Revisions'))
      ->setSubheader(
        pht('Recently updated open revisions affecting this file.'));

    $view = id(new DifferentialRevisionListView())
      ->setHeader($header)
      ->setRevisions($revisions)
      ->setUser($viewer);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    return $view;
  }

  private function loadBlame($path, $commit, $timeout) {
    $blame = $this->callConduitWithDiffusionRequest(
      'diffusion.blame',
      array(
        'commit' => $commit,
        'paths' => array($path),
        'timeout' => $timeout,
      ));

    $identifiers = idx($blame, $path, null);

    if ($identifiers) {
      $viewer = $this->getViewer();
      $drequest = $this->getDiffusionRequest();
      $repository = $drequest->getRepository();

      $commits = id(new DiffusionCommitQuery())
        ->setViewer($viewer)
        ->withRepository($repository)
        ->withIdentifiers($identifiers)
        // TODO: We only fetch this to improve author display behavior, but
        // shouldn't really need to?
        ->needCommitData(true)
        ->execute();
      $commits = mpull($commits, null, 'getCommitIdentifier');
    } else {
      $commits = array();
    }

    return array($identifiers, $commits);
  }

  private function renderCommitLinks(array $commits, $handles) {
    $links = array();
    foreach ($commits as $identifier => $commit) {
      $tooltip = $this->renderCommitTooltip(
        $commit,
        $commit->renderAuthorShortName($handles));

      $commit_link = javelin_tag(
        'a',
        array(
          'href' => $commit->getURI(),
          'sigil' => 'has-tooltip',
          'meta'  => array(
            'tip'   => $tooltip,
            'align' => 'E',
            'size'  => 600,
          ),
        ),
        $commit->getLocalName());

      $links[$identifier] = $commit_link;
    }

    return $links;
  }

  private function renderRevisionLinks(array $revisions, $handles) {
    $links = array();

    foreach ($revisions as $revision) {
      $revision_id = $revision->getID();

      $tooltip = $this->renderRevisionTooltip($revision, $handles);

      $revision_link = javelin_tag(
        'a',
        array(
          'href' => '/'.$revision->getMonogram(),
          'sigil' => 'has-tooltip',
          'meta'  => array(
            'tip'   => $tooltip,
            'align' => 'E',
            'size'  => 600,
          ),
        ),
        $revision->getMonogram());

      $links[$revision_id] = $revision_link;
    }

    return $links;
  }

  private function renderPlaintextCorpus(
    $file_corpus,
    array $blame_list,
    array $blame_commits,
    $show_blame) {

    $viewer = $this->getViewer();

    if (!$show_blame) {
      $corpus = $file_corpus;
    } else {
      $author_phids = array();
      foreach ($blame_commits as $commit) {
        $author_phid = $commit->getAuthorPHID();
        if ($author_phid === null) {
          continue;
        }
        $author_phids[$author_phid] = $author_phid;
      }

      if ($author_phids) {
        $handles = $viewer->loadHandles($author_phids);
      } else {
        $handles = array();
      }

      $authors = array();
      $names = array();
      foreach ($blame_commits as $identifier => $commit) {
        $author = $commit->renderAuthorShortName($handles);
        $name = $commit->getLocalName();

        $authors[$identifier] = $author;
        $names[$identifier] = $name;
      }

      $lines = phutil_split_lines($file_corpus);

      $rows = array();
      foreach ($lines as $line_number => $line) {
        $commit_name = null;
        $author = null;

        if (isset($blame_list[$line_number])) {
          $identifier = $blame_list[$line_number];

          if (isset($names[$identifier])) {
            $commit_name = $names[$identifier];
          }

          if (isset($authors[$identifier])) {
            $author = $authors[$identifier];
          }
        }

        $rows[] = sprintf(
          '%-10s %-20s %s',
          $commit_name,
          $author,
          $line);
      }
      $corpus = implode('', $rows);
    }

    return phutil_tag(
      'textarea',
      array(
        'style' => 'border: none; width: 100%; height: 80em; '.
                   'font-family: monospace',
      ),
      $corpus);
  }

}
