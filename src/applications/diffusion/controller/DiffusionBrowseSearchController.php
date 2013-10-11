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
      ->setNoBackground(true)
      ->appendChild($table)
      ->appendChild($pager);
  }

}
