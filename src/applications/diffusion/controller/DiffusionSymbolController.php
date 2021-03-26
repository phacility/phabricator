<?php

final class DiffusionSymbolController extends DiffusionController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    // See T13638 for discussion of escaping.
    $name = $request->getURIData('name');
    $name = phutil_unescape_uri_path_component($name);

    $query = id(new DiffusionSymbolQuery())
      ->setViewer($viewer)
      ->setName($name);

    if ($request->getStr('context')) {
      $query->setContext($request->getStr('context'));
    }

    if ($request->getStr('type')) {
      $query->setType($request->getStr('type'));
    }

    if ($request->getStr('lang')) {
      $query->setLanguage($request->getStr('lang'));
    }

    $repos = array();
    if ($request->getStr('repositories')) {
      $phids = $request->getStr('repositories');
      $phids = explode(',', $phids);
      $phids = array_filter($phids);

      if ($phids) {
        $repos = id(new PhabricatorRepositoryQuery())
          ->setViewer($request->getUser())
          ->withPHIDs($phids)
          ->execute();

        $repo_phids = mpull($repos, 'getPHID');
        if ($repo_phids) {
          $query->withRepositoryPHIDs($repo_phids);
        }
      }
    }

    $query->needPaths(true);
    $query->needRepositories(true);

    $symbols = $query->execute();

    $external_query = id(new DiffusionExternalSymbolQuery())
      ->withNames(array($name));

    if ($request->getStr('context')) {
      $external_query->withContexts(array($request->getStr('context')));
    }

    if ($request->getStr('type')) {
      $external_query->withTypes(array($request->getStr('type')));
    }

    if ($request->getStr('lang')) {
      $external_query->withLanguages(array($request->getStr('lang')));
    }

    if ($request->getStr('path')) {
      $external_query->withPaths(array($request->getStr('path')));
    }

    if ($request->getInt('line')) {
      $external_query->withLines(array($request->getInt('line')));
    }

    if ($request->getInt('char')) {
      $external_query->withCharacterPositions(
        array(
          $request->getInt('char'),
        ));
    }

    if ($repos) {
      $external_query->withRepositories($repos);
    }

    $external_sources = id(new PhutilClassMapQuery())
      ->setAncestorClass('DiffusionExternalSymbolsSource')
      ->execute();

    $results = array($symbols);
    foreach ($external_sources as $source) {
      $source_results = $source->executeQuery($external_query);

      if (!is_array($source_results)) {
        throw new Exception(
          pht(
            'Expected a list of results from external symbol source "%s".',
            get_class($source)));
      }

      try {
        assert_instances_of($source_results, 'PhabricatorRepositorySymbol');
      } catch (InvalidArgumentException $ex) {
        throw new Exception(
          pht(
            'Expected a list of PhabricatorRepositorySymbol objects '.
            'from external symbol source "%s".',
            get_class($source)));
      }

      $results[] = $source_results;
    }
    $symbols = array_mergev($results);

    if ($request->getBool('jump') && count($symbols) == 1) {
      // If this is a clickthrough from Differential, just jump them
      // straight to the target if we got a single hit.
      $symbol = head($symbols);
      return id(new AphrontRedirectResponse())
        ->setIsExternal($symbol->isExternal())
        ->setURI($symbol->getURI());
    }

    $rows = array();
    foreach ($symbols as $symbol) {
      $href = $symbol->getURI();

      if ($symbol->isExternal()) {
        $source = $symbol->getSource();
        $location = $symbol->getLocation();
      } else {
        $repo = $symbol->getRepository();
        $file = $symbol->getPath();
        $line = $symbol->getLineNumber();

        $source = $repo->getMonogram();
        $location = $file.':'.$line;
      }
      $location = phutil_tag(
        'a',
        array(
          'href' => $href,
        ),
        $location);

      $rows[] = array(
        $symbol->getSymbolType(),
        $symbol->getSymbolContext(),
        $symbol->getSymbolName(),
        $symbol->getSymbolLanguage(),
        $source,
        $location,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Type'),
        pht('Context'),
        pht('Name'),
        pht('Language'),
        pht('Source'),
        pht('Location'),
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'pri',
        '',
        '',
        '',
      ));
    $table->setNoDataString(
      pht('No matching symbol could be found in any indexed repository.'));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Similar Symbols'))
      ->setHeaderIcon('fa-bullseye');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Find Symbol'));
    $crumbs->setBorder(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $table,
      ));

    return $this->newPage()
      ->setTitle(pht('Find Symbol'))
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
