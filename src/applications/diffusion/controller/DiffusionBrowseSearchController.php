<?php

final class DiffusionBrowseSearchController extends DiffusionBrowseController {

  protected function processDiffusionRequest(AphrontRequest $request) {
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
        'title' => array(
          nonempty(basename($drequest->getPath()), '/'),
          pht(
            '%s Repository',
            $drequest->getRepository()->getCallsign()),
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

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $results = array();
        break;
      default:
        if (strlen($this->getRequest()->getStr('grep'))) {
          $search_mode = 'grep';
          $query_string = $this->getRequest()->getStr('grep');
          $results = $this->callConduitWithDiffusionRequest(
            'diffusion.searchquery',
            array(
              'grep' => $query_string,
              'commit' => $drequest->getStableCommit(),
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
              'commit' => $drequest->getStableCommit(),
              'path' => $drequest->getPath(),
              'limit' => $limit + 1,
              'offset' => $page,
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
      ->appendChild($table);

    $pager_box = id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->appendChild($pager);

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

}
