<?php

final class DiffusionBrowseController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->diffusionRequest;
    $is_file = false;

    if ($this->getRequest()->getStr('before')) {
      $is_file = true;
    } else if ($this->getRequest()->getStr('grep') == '') {
      $results = DiffusionBrowseResultSet::newFromConduit(
        $this->callConduitWithDiffusionRequest(
          'diffusion.browsequery',
          array(
            'path' => $drequest->getPath(),
            'commit' => $drequest->getCommit(),
          )));
      $reason = $results->getReasonForEmptyResultSet();
      $is_file = ($reason == DiffusionBrowseResultSet::REASON_IS_FILE);
    }

    if ($is_file) {
      $controller = new DiffusionBrowseFileController($this->getRequest());
      $controller->setDiffusionRequest($drequest);
      $controller->setCurrentApplication($this->getCurrentApplication());
      return $this->delegateToController($controller);
    }

    $content = array();

    if ($drequest->getTagContent()) {
      $title = pht('Tag: %s', $drequest->getSymbolicCommit());

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
      if (!$results->isValidResults()) {
        $empty_result = new DiffusionEmptyResultView();
        $empty_result->setDiffusionRequest($drequest);
        $empty_result->setDiffusionBrowseResultSet($results);
        $empty_result->setView($this->getRequest()->getStr('view'));
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
        $browse_table->setUser($this->getRequest()->getUser());

        $browse_panel = new AphrontPanelView();
        $browse_panel->appendChild($browse_table);
        $browse_panel->setNoBackground();

        $content[] = $browse_panel;
      }

      $content[] = $this->buildOpenRevisions();

      $readme = $this->callConduitWithDiffusionRequest(
        'diffusion.readmequery',
        array(
          'paths' => $results->getPathDicts()
        ));
      if ($readme) {
        $box = new PHUIBoxView();
        $box->setShadow(true);
        $box->appendChild($readme);
        $box->addPadding(PHUI::PADDING_LARGE);

        $panel = new AphrontPanelView();
        $panel->setHeader(pht('README'));
        $panel->setNoBackground();
        $panel->appendChild($box);

        $content[] = $panel;
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
        'device' => true,
        'dust' => true,
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
      ->setMethod('GET')
      ->setNoShading(true);

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

    $filter = new AphrontListFilterView();
    $filter->appendChild($form);

    return $filter;
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

      $results = $this->callConduitWithDiffusionRequest(
        'diffusion.searchquery',
        array(
          'grep' => $this->getRequest()->getStr('grep'),
          'stableCommitName' => $drequest->getStableCommitName(),
          'path' => $drequest->getPath(),
          'limit' => $limit + 1,
          'offset' => $page));

    } catch (ConduitException $ex) {
      $err = $ex->getErrorDescription();
      if ($err != '') {
        return id(new AphrontErrorView())
          ->setTitle(pht('Search Error'))
          ->appendChild($err);
      }
    }

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
