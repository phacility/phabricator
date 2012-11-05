<?php

final class DiffusionSymbolController extends DiffusionController {

  private $name;

  public function willProcessRequest(array $data) {
    $this->name = $data['name'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = new DiffusionSymbolQuery();
    $query->setName($this->name);

    if ($request->getStr('context') !== null) {
      $query->setContext($request->getStr('context'));
    }

    if ($request->getStr('type')) {
      $query->setType($request->getStr('type'));
    }

    if ($request->getStr('lang')) {
      $query->setLanguage($request->getStr('lang'));
    }

    if ($request->getStr('projects')) {
      $phids = $request->getStr('projects');
      $phids = explode(',', $phids);
      $phids = array_filter($phids);

      if ($phids) {
        $projects = id(new PhabricatorRepositoryArcanistProject())
          ->loadAllWhere(
            'phid IN (%Ls)',
            $phids);
        $projects = mpull($projects, 'getID');
        if ($projects) {
          $query->setProjectIDs($projects);
        }
      }
    }

    $query->needPaths(true);
    $query->needArcanistProjects(true);
    $query->needRepositories(true);

    $symbols = $query->execute();

    // For PHP builtins, jump to php.net documentation.
    if ($request->getBool('jump') && count($symbols) == 0) {
      if ($request->getStr('lang', 'php') == 'php') {
        if ($request->getStr('type', 'function') == 'function') {
          $functions = get_defined_functions();
          if (in_array($this->name, $functions['internal'])) {
            return id(new AphrontRedirectResponse())
              ->setURI('http://www.php.net/function.'.$this->name);
          }
        }
        if ($request->getStr('type', 'class') == 'class') {
          if (class_exists($this->name, false) ||
              interface_exists($this->name, false)) {
            if (id(new ReflectionClass($this->name))->isInternal()) {
              return id(new AphrontRedirectResponse())
                ->setURI('http://www.php.net/class.'.$this->name);
            }
          }
        }
      }
    }

    $rows = array();
    foreach ($symbols as $symbol) {
      $project = $symbol->getArcanistProject();
      if ($project) {
        $project_name = $project->getName();
      } else {
        $project_name = '-';
      }

      $file = phutil_escape_html($symbol->getPath());
      $line = phutil_escape_html($symbol->getLineNumber());

      $repo = $symbol->getRepository();
      if ($repo) {
        $href = $symbol->getURI();

        if ($request->getBool('jump') && count($symbols) == 1) {
          // If this is a clickthrough from Differential, just jump them
          // straight to the target if we got a single hit.
          return id(new AphrontRedirectResponse())->setURI($href);
        }

        $location = phutil_render_tag(
          'a',
          array(
            'href' => $href,
          ),
          phutil_escape_html($file.':'.$line));
      } else if ($file) {
        $location = phutil_escape_html($file.':'.$line);
      } else {
        $location = '?';
      }

      $rows[] = array(
        phutil_escape_html($symbol->getSymbolType()),
        phutil_escape_html($symbol->getSymbolContext()),
        phutil_escape_html($symbol->getSymbolName()),
        phutil_escape_html($symbol->getSymbolLanguage()),
        phutil_escape_html($project_name),
        $location,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Type',
        'Context',
        'Name',
        'Language',
        'Project',
        'File',
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
      "No matching symbol could be found in any indexed project.");

    $panel = new AphrontPanelView();
    $panel->setHeader('Similar Symbols');
    $panel->appendChild($table);

    return $this->buildStandardPageResponse(
      array(
        $panel,
      ),
      array(
        'title' => 'Find Symbol',
      ));
  }

}
