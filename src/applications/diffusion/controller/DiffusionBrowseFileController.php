<?php

final class DiffusionBrowseFileController extends DiffusionBrowseController {

  private $lintCommit;
  private $lintMessages;
  private $coverage;

  protected function processDiffusionRequest(AphrontRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $viewer = $request->getUser();

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
      'needsBlame' => $needs_blame,
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

    $file_content = DiffusionFileContent::newFromConduit(
      $this->callConduitWithDiffusionRequest(
        'diffusion.filecontentquery',
        $params));
    $data = $file_content->getCorpus();

    if ($view === 'raw') {
      return $this->buildRawResponse($path, $data);
    }

    $this->loadLintMessages();
    $this->coverage = $drequest->loadCoverage();

    if ($byte_limit && (strlen($data) == $byte_limit)) {
      $corpus = $this->buildErrorCorpus(
        pht(
          'This file is larger than %s byte(s), and too large to display '.
          'in the web UI.',
          $byte_limit));
    } else if (ArcanistDiffUtils::isHeuristicBinaryFile($data)) {
      $file = $this->loadFileForData($path, $data);
      $file_uri = $file->getBestURI();

      if ($file->isViewableImage()) {
        $corpus = $this->buildImageCorpus($file_uri);
      } else {
        $corpus = $this->buildBinaryCorpus($file_uri, $data);
      }
    } else {
      // Build the content of the file.
      $corpus = $this->buildCorpus(
        $show_blame,
        $show_color,
        $file_content,
        $needs_blame,
        $drequest,
        $path,
        $data);
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
          "File history passes through a rename from '%s' to '%s'.",
          $drequest->getPath(), $renamed));
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

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
      ),
      array(
        'title' => $basename,
      ));
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
    DiffusionFileContent $file_content,
    $needs_blame,
    DiffusionRequest $drequest,
    $path,
    $data) {

    if (!$show_color) {
      $style =
        'border: none; width: 100%; height: 80em; font-family: monospace';
      if (!$show_blame) {
        $corpus = phutil_tag(
          'textarea',
          array(
            'style' => $style,
          ),
          $file_content->getCorpus());
      } else {
        $text_list = $file_content->getTextList();
        $rev_list = $file_content->getRevList();
        $blame_dict = $file_content->getBlameDict();

        $rows = array();
        foreach ($text_list as $k => $line) {
          $rev = $rev_list[$k];
          $author = $blame_dict[$rev]['author'];
          $rows[] =
            sprintf('%-10s %-20s %s', substr($rev, 0, 7), $author, $line);
        }

        $corpus = phutil_tag(
          'textarea',
          array(
            'style' => $style,
          ),
          implode("\n", $rows));
      }
    } else {
      require_celerity_resource('syntax-highlighting-css');
      $text_list = $file_content->getTextList();
      $rev_list = $file_content->getRevList();
      $blame_dict = $file_content->getBlameDict();

      $text_list = implode("\n", $text_list);
      $text_list = PhabricatorSyntaxHighlighter::highlightWithFilename(
        $path,
        $text_list);
      $text_list = explode("\n", $text_list);

      $rows = $this->buildDisplayRows($text_list, $rev_list, $blame_dict,
        $needs_blame, $drequest, $show_blame, $show_color);

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

    $callsign = $repository->getCallsign();
    $editor_link = $user->loadEditorLink($path, $line, $callsign);
    $template = $user->loadEditorLink($path, '%l', $callsign);

    $icon_edit = id(new PHUIIconView())
      ->setIconFont('fa-pencil');
    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Open in Editor'))
      ->setHref($editor_link)
      ->setIcon($icon_edit)
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

    $iconview = id(new PHUIIconView())
      ->setIconFont($icon);
    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText($text)
      ->setHref($href)
      ->setIcon($iconview);

    return $button;
  }


  private function buildDisplayRows(
    array $text_list,
    array $rev_list,
    array $blame_dict,
    $needs_blame,
    DiffusionRequest $drequest,
    $show_blame,
    $show_color) {

    $handles = array();
    if ($blame_dict) {
      $epoch_list  = ipull(ifilter($blame_dict, 'epoch'), 'epoch');
      $epoch_min   = min($epoch_list);
      $epoch_max   = max($epoch_list);
      $epoch_range = ($epoch_max - $epoch_min) + 1;

      $author_phids = ipull(ifilter($blame_dict, 'authorPHID'), 'authorPHID');
      $handles = $this->loadViewerHandles($author_phids);
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

    $display = array();

    $line_number = 1;
    $last_rev = null;
    $color = null;
    foreach ($text_list as $k => $line) {
      $display_line = array(
        'epoch'       => null,
        'commit'      => null,
        'author'      => null,
        'target'      => null,
        'highlighted' => null,
        'line'        => $line_number,
        'data'        => $line,
      );

      if ($show_blame) {
        // If the line's rev is same as the line above, show empty content
        // with same color; otherwise generate blame info. The newer a change
        // is, the more saturated the color.

        $rev = idx($rev_list, $k, $last_rev);

        if ($last_rev == $rev) {
          $display_line['color'] = $color;
        } else {
          $blame = $blame_dict[$rev];

          if (!isset($blame['epoch'])) {
            $color = '#ffd'; // Render as warning.
          } else {
            $color_ratio = ($blame['epoch'] - $epoch_min) / $epoch_range;
            $color_value = 0xE6 * (1.0 - $color_ratio);
            $color = sprintf(
              '#%02x%02x%02x',
              $color_value,
              0xF6,
              $color_value);
          }

          $display_line['epoch'] = idx($blame, 'epoch');
          $display_line['color'] = $color;
          $display_line['commit'] = $rev;

          $author_phid = idx($blame, 'authorPHID');
          if ($author_phid && $handles[$author_phid]) {
            $author_link = $handles[$author_phid]->renderLink();
          } else {
            $author_link = $blame['author'];
          }
          $display_line['author'] = $author_link;

          $last_rev = $rev;
        }
      }

      if ($line_arr) {
        if ($line_number == $line_arr[0]['min']) {
          $display_line['target'] = true;
        }
        foreach ($line_arr as $range) {
          if ($line_number >= $range['min'] &&
              $line_number <= $range['max']) {
            $display_line['highlighted'] = true;
          }
        }
      }

      $display[] = $display_line;
      ++$line_number;
    }

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $commits = array_filter(ipull($display, 'commit'));
    if ($commits) {
      $commits = id(new DiffusionCommitQuery())
        ->setViewer($viewer)
        ->withRepository($drequest->getRepository())
        ->withIdentifiers($commits)
        ->execute();
      $commits = mpull($commits, null, 'getCommitIdentifier');
    }

    $revision_ids = id(new DifferentialRevision())
      ->loadIDsByCommitPHIDs(mpull($commits, 'getPHID'));
    $revisions = array();
    if ($revision_ids) {
      $revisions = id(new DifferentialRevisionQuery())
        ->setViewer($viewer)
        ->withIDs($revision_ids)
        ->execute();
    }

    $phids = array();
    foreach ($commits as $commit) {
      if ($commit->getAuthorPHID()) {
        $phids[] = $commit->getAuthorPHID();
      }
    }
    foreach ($revisions as $revision) {
      if ($revision->getAuthorPHID()) {
        $phids[] = $revision->getAuthorPHID();
      }
    }
    $handles = $this->loadViewerHandles($phids);

    Javelin::initBehavior('phabricator-oncopy', array());

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

    foreach ($display as $line) {

      $line_href = $drequest->generateURI(
        array(
          'action'  => 'browse',
          'line'    => $line['line'],
          'stable'  => true,
        ));

      $blame = array();
      $style = null;
      if (array_key_exists('color', $line)) {
        if ($line['color']) {
          $style = 'background: '.$line['color'].';';
        }

        $before_link = null;
        $commit_link = null;
        $revision_link = null;
        if (idx($line, 'commit')) {
          $commit = $line['commit'];

          if (idx($commits, $commit)) {
            $tooltip = $this->renderCommitTooltip(
              $commits[$commit],
              $handles,
              $line['author']);
          } else {
            $tooltip = null;
          }

          Javelin::initBehavior('phabricator-tooltips', array());
          require_celerity_resource('aphront-tooltip-css');

          $commit_link = javelin_tag(
            'a',
            array(
              'href' => $drequest->generateURI(
                array(
                  'action' => 'commit',
                  'commit' => $line['commit'],
                )),
              'sigil' => 'has-tooltip',
              'meta'  => array(
                'tip'   => $tooltip,
                'align' => 'E',
                'size'  => 600,
              ),
            ),
            id(new PhutilUTF8StringTruncator())
            ->setMaximumGlyphs(9)
            ->setTerminator('')
            ->truncateString($line['commit']));

          $revision_id = null;
          if (idx($commits, $commit)) {
            $revision_id = idx($revision_ids, $commits[$commit]->getPHID());
          }

          if ($revision_id) {
            $revision = idx($revisions, $revision_id);
            if ($revision) {
              $tooltip = $this->renderRevisionTooltip($revision, $handles);
              $revision_link = javelin_tag(
                'a',
                array(
                  'href' => '/D'.$revision->getID(),
                  'sigil' => 'has-tooltip',
                  'meta'  => array(
                    'tip'   => $tooltip,
                    'align' => 'E',
                    'size'  => 600,
                  ),
                ),
                'D'.$revision->getID());
            }
          }

          $uri = $line_href->alter('before', $commit);
          $before_link = javelin_tag(
            'a',
            array(
              'href'  => $uri->setQueryParam('view', 'blame'),
              'sigil' => 'has-tooltip',
              'meta'  => array(
                'tip'     => pht('Skip Past This Commit'),
                'align'   => 'E',
                'size'    => 300,
              ),
            ),
            "\xC2\xAB");
        }

        $blame[] = phutil_tag(
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

        $blame[] = phutil_tag(
          'th',
          array(
            'class' => 'diffusion-rev-link',
          ),
          $object_links);
      }

      $line_link = phutil_tag(
        'a',
        array(
          'href' => $line_href,
          'style' => $style,
        ),
        $line['line']);

      $blame[] = javelin_tag(
        'th',
        array(
          'class' => 'diffusion-line-link',
          'sigil' => 'phabricator-source-line',
          'style' => $style,
        ),
        $line_link);

      Javelin::initBehavior('phabricator-line-linker');

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

      $blame[] = phutil_tag(
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
        $cov_index = $line['line'] - 1;

        if (isset($this->coverage[$cov_index])) {
          $cov_class = $this->coverage[$cov_index];
        } else {
          $cov_class = 'N';
        }

        $blame[] = phutil_tag(
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
        $blame);

      $cur_inlines = $this->renderInlines(
        idx($inlines, $line['line'], array()),
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
    $needs_blame,
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

      $row = array_fill(0, ($needs_blame ? 3 : 1), phutil_tag('th'));

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

  private function loadFileForData($path, $data) {
    $file = PhabricatorFile::buildFromFileDataOrHash(
      $data,
      array(
        'name' => basename($path),
        'ttl' => time() + 60 * 60 * 24,
        'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
      ));

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $file->attachToObject(
        $this->getDiffusionRequest()->getRepository()->getPHID());
    unset($unguarded);

    return $file;
  }

  private function buildRawResponse($path, $data) {
    $file = $this->loadFileForData($path, $data);
    return $file->getRedirectResponse();
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
    array $handles) {
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
    array $handles,
    $author) {

    $viewer = $this->getRequest()->getUser();

    $date = phabricator_date($commit->getEpoch(), $viewer);
    $summary = trim($commit->getSummary());

    if ($commit->getAuthorPHID()) {
      $author = $handles[$commit->getAuthorPHID()]->getName();
    }

    return "{$summary}\n{$date} \xC2\xB7 {$author}";
  }

}
