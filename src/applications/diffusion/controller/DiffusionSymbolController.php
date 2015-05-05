<?php

final class DiffusionSymbolController extends DiffusionController {

  private $name;

  protected function processDiffusionRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $this->name = $request->getURIData('name');

    $query = id(new DiffusionSymbolQuery())
      ->setViewer($user)
      ->setName($this->name);

    if ($request->getStr('context') !== null) {
      $query->setContext($request->getStr('context'));
    }

    if ($request->getStr('type')) {
      $query->setType($request->getStr('type'));
    }

    if ($request->getStr('lang')) {
      $query->setLanguage($request->getStr('lang'));
    }

    if ($request->getStr('repositories')) {
      $phids = $request->getStr('repositories');
      $phids = explode(',', $phids);
      $phids = array_filter($phids);

      if ($phids) {
        $repos = id(new PhabricatorRepositoryQuery())
          ->setViewer($request->getUser())
          ->withPHIDs($phids)
          ->execute();

        $repos = mpull($repos, 'getPHID');
        if ($repos) {
          $query->withRepositoryPHIDs($repos);
        }
      }
    }

    $query->needPaths(true);
    $query->needRepositories(true);

    $symbols = $query->execute();

    // For PHP builtins, jump to php.net documentation.
    if ($request->getBool('jump') && count($symbols) == 0) {
      if ($request->getStr('lang', 'php') == 'php') {
        if ($request->getStr('type', 'function') == 'function') {
          $functions = get_defined_functions();
          if (in_array($this->name, $functions['internal'])) {
            return id(new AphrontRedirectResponse())
              ->setIsExternal(true)
              ->setURI('http://www.php.net/function.'.$this->name);
          }
        }
        if ($request->getStr('type', 'class') == 'class') {
          if (class_exists($this->name, false) ||
              interface_exists($this->name, false)) {
            if (id(new ReflectionClass($this->name))->isInternal()) {
              return id(new AphrontRedirectResponse())
                ->setIsExternal(true)
                ->setURI('http://www.php.net/class.'.$this->name);
            }
          }
        }
      }
    }

    $rows = array();
    foreach ($symbols as $symbol) {
      $file = $symbol->getPath();
      $line = $symbol->getLineNumber();

      $repo = $symbol->getRepository();
      if ($repo) {
        $href = $symbol->getURI();

        if ($request->getBool('jump') && count($symbols) == 1) {
          // If this is a clickthrough from Differential, just jump them
          // straight to the target if we got a single hit.
          return id(new AphrontRedirectResponse())->setURI($href);
        }

        $location = phutil_tag(
          'a',
          array(
            'href' => $href,
          ),
          $file.':'.$line);
      } else if ($file) {
        $location = $file.':'.$line;
      } else {
        $location = '?';
      }

      $rows[] = array(
        $symbol->getSymbolType(),
        $symbol->getSymbolContext(),
        $symbol->getSymbolName(),
        $symbol->getSymbolLanguage(),
        $repo->getMonogram(),
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
        pht('Repository'),
        pht('File'),
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
      pht('No matching symbol could be found in any indexed project.'));

    $panel = new PHUIObjectBoxView();
    $panel->setHeaderText(pht('Similar Symbols'));
    $panel->appendChild($table);

    return $this->buildApplicationPage(
      $panel,
      array(
        'title' => pht('Find Symbol'),
      ));
  }

}
