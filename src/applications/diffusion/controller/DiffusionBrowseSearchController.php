<?php

final class DiffusionBrowseSearchController extends DiffusionBrowseController {

  public function processRequest() {
    $drequest = $this->diffusionRequest;

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

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
      ),
      array(
        'device' => true,
        'title' => array(
          nonempty(basename($drequest->getPath()), '/'),
          $drequest->getRepository()->getCallsign().' Repository',
        ),
      ));
  }

  private function renderSearchResults() {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $results = array();

    $limit = 100;
    $page = $this->getRequest()->getInt('page', 0);
    $pager = new AphrontPagerView();
    $pager->setPageSize($limit);
    $pager->setOffset($page);
    $pager->setURI($this->getRequest()->getRequestURI(), 'page');

    $search_mode = null;

    try {
      if (strlen($this->getRequest()->getStr('grep'))) {
        $search_mode = 'grep';
        $query_string = $this->getRequest()->getStr('grep');
        $results = $this->callConduitWithDiffusionRequest(
          'diffusion.searchquery',
          array(
            'grep' => $query_string,
            'stableCommitName' => $drequest->getStableCommitName(),
            'path' => $drequest->getPath(),
            'limit' => $limit + 1,
            'offset' => $page,
          ));
      } else { // Filename search.
        $search_mode = 'find';
        $query_string = $this->getRequest()->getStr('find');
        $results = $this->callConduitWithDiffusionRequest(
          'diffusion.querypaths',
          array(
            'pattern' => $query_string,
            'commit' => $drequest->getStableCommitName(),
            'path' => $drequest->getPath(),
            'limit' => $limit + 1,
            'offset' => $page,
          ));
      }
    } catch (ConduitException $ex) {
      $err = $ex->getErrorDescription();
      if ($err != '') {
        return id(new AphrontErrorView())
          ->setTitle(pht('Search Error'))
          ->appendChild($err);
      }
    }

    $results = $pager->sliceResults($results);

    if ($search_mode == 'grep') {
      $table = $this->renderGrepResults($results);
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
      ->appendChild($table);

    $pager_box = id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->appendChild($pager);

    return array($box, $pager_box);
  }

  private function renderGrepResults(array $results) {
    $drequest = $this->getDiffusionRequest();

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

}
