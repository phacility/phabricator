<?php

final class DiffusionBrowseFileController extends DiffusionController {

  private $corpusType = 'text';

  private $lintCommit;
  private $lintMessages;

  public function processRequest() {

    $request = $this->getRequest();
    $drequest = $this->getDiffusionRequest();

    $before = $request->getStr('before');
    if ($before) {
      return $this->buildBeforeResponse($before);
    }

    $path = $drequest->getPath();

    $selected = $request->getStr('view');
    $preferences = $request->getUser()->loadPreferences();
    if (!$selected) {
      $selected = $preferences->getPreference(
        PhabricatorUserPreferences::PREFERENCE_DIFFUSION_VIEW,
        'highlighted');
    } else if ($request->isFormPost() && $selected != 'raw') {
      $preferences->setPreference(
        PhabricatorUserPreferences::PREFERENCE_DIFFUSION_VIEW,
        $selected);
      $preferences->save();

      return id(new AphrontRedirectResponse())
        ->setURI($request->getRequestURI()->alter('view', $selected));
    }

    $needs_blame = ($selected == 'plainblame');
    if ($selected == 'blame' && $request->isAjax()) {
      $needs_blame = true;
    }

    $file_query = DiffusionFileContentQuery::newFromDiffusionRequest(
      $this->diffusionRequest);
    $file_query->setViewer($request->getUser());
    $file_query->setNeedsBlame($needs_blame);
    $file_query->loadFileContent();
    $data = $file_query->getRawData();

    if ($selected === 'raw') {
      return $this->buildRawResponse($path, $data);
    }

    $this->loadLintMessages();

    // Build the content of the file.
    $corpus = $this->buildCorpus(
      $selected,
      $file_query,
      $needs_blame,
      $drequest,
      $path,
      $data);

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())->setContent($corpus);
    }

    require_celerity_resource('diffusion-source-css');

    if ($this->corpusType == 'text') {
      $view_select_panel = $this->renderViewSelectPanel($selected);
    } else {
      $view_select_panel = null;
    }

    // Render the page.
    $content = array();

    $follow  = $request->getStr('follow');
    if ($follow) {
      $notice = new AphrontErrorView();
      $notice->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $notice->setTitle('Unable to Continue');
      switch ($follow) {
        case 'first':
          $notice->appendChild(
            "Unable to continue tracing the history of this file because ".
            "this commit is the first commit in the repository.");
          break;
        case 'created':
          $notice->appendChild(
            "Unable to continue tracing the history of this file because ".
            "this commit created the file.");
          break;
      }
      $content[] = $notice;
    }

    $renamed = $request->getStr('renamed');
    if ($renamed) {
      $notice = new AphrontErrorView();
      $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
      $notice->setTitle('File Renamed');
      $notice->appendChild(
        "File history passes through a rename from '".$drequest->getPath().
        "' to '".$renamed."'.");
      $content[] = $notice;
    }

    $content[] = $view_select_panel;
    $content[] = $corpus;
    $content[] = $this->buildOpenRevisions();

    $nav = $this->buildSideNav('browse', true);
    $nav->appendChild($content);
    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));
    $nav->setCrumbs($crumbs);

    $basename = basename($this->getDiffusionRequest()->getPath());

    return $this->buildApplicationPage(
      $nav,
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

  private function buildCorpus($selected,
                               DiffusionFileContentQuery $file_query,
                               $needs_blame,
                               DiffusionRequest $drequest,
                               $path,
                               $data) {

    if (ArcanistDiffUtils::isHeuristicBinaryFile($data)) {
      $file = $this->loadFileForData($path, $data);
      $file_uri = $file->getBestURI();

      if ($file->isViewableImage()) {
        $this->corpusType = 'image';
        return $this->buildImageCorpus($file_uri);
      } else {
        $this->corpusType = 'binary';
        return $this->buildBinaryCorpus($file_uri, $data);
      }
    }

    switch ($selected) {
      case 'plain':
        $style =
          "margin: 1em 2em; width: 90%; height: 80em; font-family: monospace";
        $corpus = phutil_tag(
          'textarea',
          array(
            'style' => $style,
          ),
          $file_query->getRawData());

          break;

      case 'plainblame':
        $style =
          "margin: 1em 2em; width: 90%; height: 80em; font-family: monospace";
        list($text_list, $rev_list, $blame_dict) =
          $file_query->getBlameData();

        $rows = array();
        foreach ($text_list as $k => $line) {
          $rev = $rev_list[$k];
          $author = $blame_dict[$rev]['author'];
          $rows[] =
            sprintf("%-10s %-20s %s", substr($rev, 0, 7), $author, $line);
        }

        $corpus = phutil_tag(
          'textarea',
          array(
            'style' => $style,
          ),
          implode("\n", $rows));

        break;

      case 'highlighted':
      case 'blame':
      default:
        require_celerity_resource('syntax-highlighting-css');

        list($text_list, $rev_list, $blame_dict) = $file_query->getBlameData();

        $text_list = implode("\n", $text_list);
        $text_list = PhabricatorSyntaxHighlighter::highlightWithFilename(
          $path,
          $text_list);
        $text_list = explode("\n", $text_list);

        $rows = $this->buildDisplayRows($text_list, $rev_list, $blame_dict,
          $needs_blame, $drequest, $file_query, $selected);

        $corpus_table = javelin_tag(
          'table',
          array(
            'class' => "diffusion-source remarkup-code PhabricatorMonospaced",
            'sigil' => 'diffusion-source',
          ),
          $rows);

        if ($this->getRequest()->isAjax()) {
          return $corpus_table;
        }

        $id = celerity_generate_unique_node_id();

        $projects = $drequest->loadArcanistProjects();
        $langs = array();
        foreach ($projects as $project) {
          $ls = $project->getSymbolIndexLanguages();
          if (!$ls) {
            continue;
          }
          $dep_projects = $project->getSymbolIndexProjects();
          $dep_projects[] = $project->getPHID();
          foreach ($ls as $lang) {
            if (!isset($langs[$lang])) {
              $langs[$lang] = array();
            }
            $langs[$lang] += $dep_projects + array($project);
          }
        }

        $lang = last(explode('.', $drequest->getPath()));

        if (isset($langs[$lang])) {
          Javelin::initBehavior(
            'repository-crossreference',
            array(
              'container' => $id,
              'lang' => $lang,
              'projects' => $langs[$lang],
            ));
        }

        $corpus = phutil_tag(
          'div',
          array(
            'style' => 'padding: 0 2em;',
            'id' => $id,
          ),
          $corpus_table);

        Javelin::initBehavior('load-blame', array('id' => $id));

        break;
    }

    return $corpus;
  }

  private function renderViewSelectPanel($selected) {
    $toggle_blame = array(
      'highlighted'   => 'blame',
      'blame'         => 'highlighted',
      'plain'         => 'plainblame',
      'plainblame'    => 'plain',
      'raw'           => 'raw',  // not a real case.
    );
    $toggle_highlight = array(
      'highlighted'   => 'plain',
      'blame'         => 'plainblame',
      'plain'         => 'highlighted',
      'plainblame'    => 'blame',
      'raw'           => 'raw',  // not a real case.
    );

    $user = $this->getRequest()->getUser();
    $base_uri = $this->getRequest()->getRequestURI();

    $blame_on = ($selected == 'blame' || $selected == 'plainblame');
    if ($blame_on) {
      $blame_text = pht('Disable Blame');
    } else {
      $blame_text = pht('Enable Blame');
    }

    $blame_button = $this->createViewAction(
      $blame_text,
      $base_uri->alter('view', $toggle_blame[$selected]),
      $user);


    $highlight_on = ($selected == 'blame' || $selected == 'highlighted');
    if ($highlight_on) {
      $highlight_text = pht('Disable Highlighting');
    } else {
      $highlight_text = pht('Enable Highlighting');
    }
    $highlight_button = $this->createViewAction(
      $highlight_text,
      $base_uri->alter('view', $toggle_highlight[$selected]),
      $user);


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

    $lint_button = $this->createViewAction(
      $lint_text,
      $href,
      $user);

    if (!$href) {
      $lint_button->setDisabled(true);
    }


    $raw_button = $this->createViewAction(
      pht('View Raw File'),
      $base_uri->alter('view', 'raw'),
      $user,
      'file');

    $edit_button = $this->createEditAction();

    return id(new PhabricatorActionListView())
      ->setUser($user)
      ->addAction($blame_button)
      ->addAction($highlight_button)
      ->addAction($lint_button)
      ->addAction($raw_button)
      ->addAction($edit_button);
  }

  private function createViewAction(
    $localized_text,
    $href,
    $user,
    $icon = null) {

    return id(new PhabricatorActionView())
          ->setName($localized_text)
          ->setIcon($icon)
          ->setUser($user)
          ->setRenderAsForm(true)
          ->setHref($href);
  }

  private function createEditAction() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $drequest = $this->getDiffusionRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $line = nonempty((int)$drequest->getLine(), 1);

    $callsign = $repository->getCallsign();
    $editor_link = $user->loadEditorLink($path, $line, $callsign);

    $action = id(new PhabricatorActionView())
      ->setName(pht('Open in Editor'))
      ->setIcon('edit');

    $action->setHref($editor_link);
    $action->setDisabled(!$editor_link);

    return $action;
  }

  private function buildDisplayRows(
    array $text_list,
    array $rev_list,
    array $blame_dict,
    $needs_blame,
    DiffusionRequest $drequest,
    DiffusionFileContentQuery $file_query,
    $selected) {

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

      if ($selected == 'blame') {
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
            $author_link = phutil_tag(
              'span',
              array(
              ),
              $blame['author']);
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

    $commits = array_filter(ipull($display, 'commit'));
    if ($commits) {
      $commits = id(new PhabricatorAuditCommitQuery())
        ->withIdentifiers($drequest->getRepository()->getID(), $commits)
        ->needCommitData(true)
        ->execute();
      $commits = mpull($commits, null, 'getCommitIdentifier');
    }

    $revision_ids = id(new DifferentialRevision())
      ->loadIDsByCommitPHIDs(mpull($commits, 'getPHID'));
    $revisions = array();
    if ($revision_ids) {
      $revisions = id(new DifferentialRevision())->loadAllWhere(
        'id IN (%Ld)',
        $revision_ids);
    }

    $request = $this->getRequest();
    $user = $request->getUser();

    Javelin::initBehavior('phabricator-oncopy', array());

    $engine = null;
    $inlines = array();
    if ($this->getRequest()->getStr('lint') !== null && $this->lintMessages) {
      $engine = new PhabricatorMarkupEngine();
      $engine->setViewer($user);

      foreach ($this->lintMessages as $message) {
        $inline = id(new PhabricatorAuditInlineComment())
          ->setID($message['id'])
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
      ($selected == 'blame'),
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

          $summary = 'Unknown';
          if (idx($commits, $commit)) {
            $summary = $commits[$commit]->getCommitData()->getSummary();
          }

          $tooltip = phabricator_date(
            $line['epoch'],
            $user)." \xC2\xB7 ".$summary;

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
            phutil_utf8_shorten($line['commit'], 9, ''));

          $revision_id = null;
          if (idx($commits, $commit)) {
            $revision_id = idx($revision_ids, $commits[$commit]->getPHID());
          }

          if ($revision_id) {
            $revision = idx($revisions, $revision_id);
            if (!$revision) {
              $tooltip = '(Invalid revision)';
            } else {
              $tooltip =
                phabricator_date($revision->getDateModified(), $user).
                " \xC2\xB7 ".
                $revision->getTitle();
            }
            $revision_link = javelin_tag(
              'a',
              array(
                'href' => '/D'.$revision_id,
                'sigil' => 'has-tooltip',
                'meta'  => array(
                  'tip'   => $tooltip,
                  'align' => 'E',
                  'size'  => 600,
                ),
              ),
              'D'.$revision_id);
          }

          $uri = $line_href->alter('before', $commit);
          $before_link = javelin_tag(
            'a',
            array(
              'href'  => $uri->setQueryParam('view', 'blame'),
              'sigil' => 'has-tooltip',
              'meta'  => array(
                'tip'     => 'Skip Past This Commit',
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
            'style' => $style,
          ),
          $before_link);

        $blame[] = phutil_tag(
          'th',
          array(
            'class' => 'diffusion-rev-link',
            'style' => $style,
          ),
          $commit_link);

        $blame[] = phutil_tag(
          'th',
          array(
            'class' => 'diffusion-rev-link',
            'style' => $style,
          ),
          $revision_link);

        $blame[] = phutil_tag(
          'th',
          array(
            'class' => 'diffusion-author-link',
            'style' => $style,
          ),
          idx($line, 'author'));
      }

      $line_link = phutil_tag(
        'a',
        array(
          'href' => $line_href,
        ),
        $line['line']);

      $blame[] = javelin_tag(
        'th',
        array(
          'class' => 'diffusion-line-link',
          'sigil' => 'diffusion-line-link',
          'style' => $style,
        ),
        $line_link);

      Javelin::initBehavior('diffusion-line-linker');

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
          phutil_safe_html($line['data']),
        ));

      $rows[] = phutil_tag(
        'tr',
        array(
          'class' => ($line['highlighted'] ? 'highlighted' : null),
        ),
        $blame);

      $rows = array_merge($rows, $this->renderInlines(
        idx($inlines, $line['line'], array()),
        ($selected == 'blame'),
        $engine));
    }

    return $rows;
  }

  private function renderInlines(array $inlines, $needs_blame, $engine) {
    $rows = array();
    foreach ($inlines as $inline) {
      $inline_view = id(new DifferentialInlineCommentView())
        ->setMarkupEngine($engine)
        ->setInlineComment($inline)
        ->render();
      $row = array_fill(0, ($needs_blame ? 5 : 1), phutil_tag('th'));
      $row[] = phutil_tag('td', array(), $inline_view);
      $rows[] = phutil_tag('tr', array('class' => 'inline'), $row);
    }
    return $rows;
  }

  private function loadFileForData($path, $data) {
    return PhabricatorFile::buildFromFileDataOrHash(
      $data,
      array(
        'name' => basename($path),
        'ttl' => time() + 60 * 60 * 24,
      ));
  }

  private function buildRawResponse($path, $data) {
    $file = $this->loadFileForData($path, $data);
    return id(new AphrontRedirectResponse())->setURI($file->getBestURI());
  }

  private function buildImageCorpus($file_uri) {
    $properties = new PhabricatorPropertyListView();

    $properties->addProperty(
      pht('Image'),
      phutil_tag(
        'img',
        array(
          'src' => $file_uri,
        )));

    $actions = id(new PhabricatorActionListView())
      ->setUser($this->getRequest()->getUser())
      ->addAction($this->createEditAction());

    return array($actions, $properties);
  }

  private function buildBinaryCorpus($file_uri, $data) {
    $properties = new PhabricatorPropertyListView();

    $size = strlen($data);
    $properties->addTextContent(
      pht(
        'This is a binary file. It is %s byte(s) in length.',
        new PhutilNumber($size)));

    $actions = id(new PhabricatorActionListView())
      ->setUser($this->getRequest()->getUser())
      ->addAction($this->createEditAction())
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Download Binary File...'))
          ->setIcon('download')
          ->setHref($file_uri));

    return array($actions, $properties);

  }

  private function buildBeforeResponse($before) {
    $request = $this->getRequest();
    $drequest = $this->getDiffusionRequest();

    // NOTE: We need to get the grandparent so we can capture filename changes
    // in the parent.

    $parent = $this->loadParentRevisionOf($before);
    $old_filename = null;
    $was_created = false;
    if ($parent) {
      $grandparent = $this->loadParentRevisionOf(
        $parent->getCommitIdentifier());

      if ($grandparent) {
        $rename_query = new DiffusionRenameHistoryQuery();
        $rename_query->setRequest($drequest);
        $rename_query->setOldCommit($grandparent->getCommitIdentifier());
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
      $target_commit = $parent->getCommitIdentifier();
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

    $diff_query = DiffusionRawDiffQuery::newFromDiffusionRequest($drequest);
    $diff_query->setAgainstCommit($target_commit);
    try {
      $raw_diff = $diff_query->loadRawDiff();
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

    } catch (Exception $ex) {
      return $line;
    }
  }

  private function loadParentRevisionOf($commit) {
    $drequest = $this->getDiffusionRequest();

    $before_req = DiffusionRequest::newFromDictionary(
      array(
        'repository' => $drequest->getRepository(),
        'commit'     => $commit,
      ));

    $query = DiffusionCommitParentsQuery::newFromDiffusionRequest($before_req);
    $parents = $query->loadParents();

    return head($parents);
  }

}
