<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class DiffusionSymbolController extends DiffusionController {

  private $name;

  public function willProcessRequest(array $data) {
    $this->name = $data['name'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = new DiffusionSymbolQuery();
    $query->setName($this->name);

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

    $symbols = $query->execute();

    // For PHP builtins, jump to php.net documentation.
    if ($request->getBool('jump') && count($symbols) == 0) {
      if ($request->getStr('lang') == 'php') {
        if ($request->getStr('type') == 'function') {
          if (in_array($this->name, idx(get_defined_functions(), 'internal'))) {
            return id(new AphrontRedirectResponse())
              ->setURI('http://www.php.net/'.$this->name);
          }
        }
      }
    }

    if ($symbols) {
      $projects = id(new PhabricatorRepositoryArcanistProject())->loadAllWhere(
        'id IN (%Ld)',
        mpull($symbols, 'getArcanistProjectID', 'getID'));
    } else {
      $projects = array();
    }

    $path_map = array();
    if ($symbols) {
      $path_map = queryfx_all(
        id(new PhabricatorRepository())->establishConnection('r'),
        'SELECT * FROM %T WHERE id IN (%Ld)',
        PhabricatorRepository::TABLE_PATH,
        mpull($symbols, 'getPathID'));
      $path_map = ipull($path_map, 'path', 'id');
    }

    $repo_ids = array_filter(mpull($projects, 'getRepositoryID'));
    if ($repo_ids) {
      $repos = id(new PhabricatorRepository())->loadAllWhere(
        'id IN (%Ld)',
        $repo_ids);
    } else {
      $repos = array();
    }

    $rows = array();
    foreach ($symbols as $symbol) {
      $project = idx($projects, $symbol->getArcanistProjectID());
      if ($project) {
        $project_name = $project->getName();
      } else {
        $project_name = '-';
      }

      $file = phutil_escape_html(idx($path_map, $symbol->getPathID()));
      $line = phutil_escape_html($symbol->getLineNumber());

      $repo = idx($repos, $project->getRepositoryID());
      if ($repo) {

        $drequest = DiffusionRequest::newFromAphrontRequestDictionary(
          array(
            'callsign' => $repo->getCallsign(),
          ));
        $branch = $drequest->getBranchURIComponent($drequest->getBranch());
        $file = $branch.ltrim($file, '/');

        $href = '/diffusion/'.$repo->getCallsign().'/browse/'.$file.'$'.$line;

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
        'Name',
        'Language',
        'Project',
        'File',
      ));
    $table->setColumnClasses(
      array(
        '',
        'pri',
        '',
        '',
        '',
        'n'
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
