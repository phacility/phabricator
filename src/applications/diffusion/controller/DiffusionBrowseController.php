<?php

final class DiffusionBrowseController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->diffusionRequest;
    $is_file = false;

    if ($this->getRequest()->getStr('before')) {
      $is_file = true;
    } else if ($this->getRequest()->getStr('grep') == '') {
      $browse_query = DiffusionBrowseQuery::newFromDiffusionRequest($drequest);
      $browse_query->setViewer($this->getRequest()->getUser());
      $results = $browse_query->loadPaths();
      $reason = $browse_query->getReasonForEmptyResultSet();
      $is_file = ($reason == DiffusionBrowseQuery::REASON_IS_FILE);
    }

    if ($is_file) {
      $controller = new DiffusionBrowseFileController($this->getRequest());
      $controller->setDiffusionRequest($drequest);
      $controller->setCurrentApplication($this->getCurrentApplication());
      return $this->delegateToController($controller);
    }

    $content = array();

    if ($drequest->getTagContent()) {
      $title = 'Tag: '.$drequest->getSymbolicCommit();

      $tag_view = new AphrontPanelView();
      $tag_view->setHeader($title);
      $tag_view->appendChild(
        $this->markupText($drequest->getTagContent()));

      $content[] = $tag_view;
    }

    $content[] = $this->renderSearchForm();

    if ($this->getRequest()->getStr('grep') != '') {
      $content[] = $this->renderSearchResults();

    } else {
      if (!$results) {
        $empty_result = new DiffusionEmptyResultView();
        $empty_result->setDiffusionRequest($drequest);
        $empty_result->setBrowseQuery($browse_query);
        $empty_result->setView($this->getRequest()->getStr('view'));
        $content[] = $empty_result;

      } else {

        $phids = array();
        foreach ($results as $result) {
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
        $browse_table->setPaths($results);
        $browse_table->setUser($this->getRequest()->getUser());

        $browse_panel = new AphrontPanelView();
        $browse_panel->appendChild($browse_table);
        $browse_panel->setNoBackground();

        $content[] = $browse_panel;
      }

      $content[] = $this->buildOpenRevisions();

      $readme_content = $browse_query->renderReadme($results);
      if ($readme_content) {
        $readme_panel = new AphrontPanelView();
        $readme_panel->setHeader('README');
        $readme_panel->appendChild($readme_content);

        $content[] = $readme_panel;
      }
    }

    $nav = $this->buildSideNav('browse', false);
    $nav->appendChild($content);

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => array(
          nonempty(basename($drequest->getPath()), '/'),
          $drequest->getRepository()->getCallsign().' Repository',
        ),
      ));
  }


  private function renderSearchForm() {
    $drequest = $this->getDiffusionRequest();
    $form = id(new AphrontFormView())
      ->setUser($this->getRequest()->getUser())
      ->setMethod('GET');

    switch ($drequest->getRepository()->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $form->appendChild(pht('Search is not available in Subversion.'));
        break;

      default:
        $form
          ->appendChild(
            id(new AphrontFormTextControl())
              ->setLabel(pht('Search Here'))
              ->setName('grep')
              ->setValue($this->getRequest()->getStr('grep'))
              ->setCaption(pht('Regular expression')))
          ->appendChild(
            id(new AphrontFormSubmitControl())
              ->setValue(pht('Grep')));
        break;
    }

    return $form;
  }

  private function renderSearchResults() {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $results = array();
    $no_data = pht('No results found.');

    $limit = 100;
    $page = $this->getRequest()->getInt('page', 0);
    $pager = new AphrontPagerView();
    $pager->setPageSize($limit);
    $pager->setOffset($page);
    $pager->setURI($this->getRequest()->getRequestURI(), 'page');

    try {

      switch ($repository->getVersionControlSystem()) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $future = $repository->getLocalCommandFuture(
            // NOTE: --perl-regexp is available only with libpcre compiled in.
            'grep --extended-regexp --null -n --no-color -e %s %s -- %s',
            $this->getRequest()->getStr('grep'),
            $drequest->getStableCommitName(),
            $drequest->getPath());

          $binary_pattern = '/Binary file [^:]*:(.+) matches/';
          $lines = new LinesOfALargeExecFuture($future);
          foreach ($lines as $line) {
            $result = null;
            if (preg_match('/[^:]*:(.+)\0(.+)\0(.*)/', $line, $result)) {
              $results[] = array_slice($result, 1);
            } else if (preg_match($binary_pattern, $line, $result)) {
              list(, $path) = $result;
              $results[] = array($path, null, pht('Binary file'));
            } else {
              $results[] = array(null, null, $line);
            }
            if (count($results) > $page + $limit) {
              break;
            }
          }
          unset($lines);

          break;

        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $future = $repository->getLocalCommandFuture(
            'grep --rev %s --print0 --line-number %s %s',
            hgsprintf('ancestors(%s)', $drequest->getStableCommitName()),
            $this->getRequest()->getStr('grep'),
            $drequest->getPath());

          $lines = id(new LinesOfALargeExecFuture($future))->setDelimiter("\0");
          $parts = array();
          foreach ($lines as $line) {
            $parts[] = $line;
            if (count($parts) == 4) {
              list($path, $offset, $line, $string) = $parts;
              $results[] = array($path, $line, $string);
              if (count($results) > $page + $limit) {
                break;
              }
              $parts = array();
            }
          }
          unset($lines);

          break;
      }

    } catch (CommandException $ex) {
      $stderr = $ex->getStderr();
      if ($stderr != '') {
        return id(new AphrontErrorView())
          ->setTitle(pht('Search Error'))
          ->appendChild($stderr);
      }
    }

    $results = array_slice($results, $page);
    $results = $pager->sliceResults($results);

    require_celerity_resource('syntax-highlighting-css');

    // NOTE: This can be wrong because we may find the string inside the
    // comment. But it's correct in most cases and highlighting the whole file
    // would be too expensive.
    $futures = array();
    $engine = PhabricatorSyntaxHighlighter::newEngine();
    foreach ($results as $result) {
      list($path, $line, $string) = $result;
      $futures["{$path}:{$line}"] = $engine->getHighlightFuture(
        $engine->getLanguageFromFilename($path),
        ltrim($string));
    }

    try {
      Futures($futures)->limit(8)->resolveAll();
    } catch (PhutilSyntaxHighlighterException $ex) {
    }

    $rows = array();
    foreach ($results as $result) {
      list($path, $line, $string) = $result;

      $href = $drequest->generateURI(array(
        'action' => 'browse',
        'path' => $path,
        'line' => $line,
      ));

      try {
        $string = $futures["{$path}:{$line}"]->resolve();
      } catch (PhutilSyntaxHighlighterException $ex) {
      }

      $string = phutil_tag(
        'pre',
        array('class' => 'PhabricatorMonospaced'),
        $string);

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
      ->setNoDataString($no_data);

    return id(new AphrontPanelView())
      ->setHeader(pht('Search Results'))
      ->appendChild($table)
      ->appendChild($pager);
  }


  private function markupText($text) {
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

}
