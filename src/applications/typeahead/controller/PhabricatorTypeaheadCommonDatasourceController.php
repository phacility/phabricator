<?php

/*
 * Copyright 2012 Facebook, Inc.
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

final class PhabricatorTypeaheadCommonDatasourceController
  extends PhabricatorTypeaheadDatasourceController {

  private $type;

  public function willProcessRequest(array $data) {
    $this->type = $data['type'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $query = $request->getStr('q');

    $need_rich_data = false;

    $need_users = false;
    $need_applications = false;
    $need_all_users = false;
    $need_lists = false;
    $need_projs = false;
    $need_repos = false;
    $need_packages = false;
    $need_upforgrabs = false;
    $need_arcanist_projects = false;
    $need_noproject = false;
    $need_symbols = false;
    switch ($this->type) {
      case 'mainsearch':
        $need_users = true;
        $need_applications = true;
        $need_rich_data = true;
        $need_symbols = true;
        break;
      case 'searchowner':
        $need_users = true;
        $need_upforgrabs = true;
        break;
      case 'searchproject':
        $need_projs = true;
        $need_noproject = true;
        break;
      case 'users':
        $need_users = true;
        break;
      case 'mailable':
        $need_users = true;
        $need_lists = true;
        break;
      case 'allmailable':
        $need_users = true;
        $need_all_users = true;
        $need_lists = true;
        break;
      case 'projects':
        $need_projs = true;
        break;
      case 'usersorprojects':
        $need_users = true;
        $need_projs = true;
        break;
      case 'repositories':
        $need_repos = true;
        break;
      case 'packages':
        $need_packages = true;
        break;
      case 'accounts':
        $need_users = true;
        $need_all_users = true;
        break;
      case 'arcanistprojects':
        $need_arcanist_projects = true;
        break;
    }

    $results = array();

    if ($need_upforgrabs) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName('upforgrabs (Up For Grabs)')
        ->setPHID(ManiphestTaskOwner::OWNER_UP_FOR_GRABS);
    }

    if ($need_noproject) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName('noproject (No Project)')
        ->setPHID(ManiphestTaskOwner::PROJECT_NO_PROJECT);
    }

    if ($need_users) {
      $columns = array(
        'isSystemAgent',
        'isAdmin',
        'isDisabled',
        'userName',
        'realName',
        'phid');

      if ($query) {
        // This is an arbitrary limit which is just larger than any limit we
        // actually use in the application.

        // TODO: The datasource should pass this in the query.
        $limit = 15;

        $user_table = new PhabricatorUser();
        $conn_r = $user_table->establishConnection('r');
        $ids = queryfx_all(
          $conn_r,
          'SELECT id FROM %T WHERE username LIKE %>
            ORDER BY username ASC LIMIT %d',
          $user_table->getTableName(),
          $query,
          $limit);
        $ids = ipull($ids, 'id');

        if (count($ids) < $limit) {
          // If we didn't find enough username hits, look for real name hits.
          // We need to pull the entire pagesize so that we end up with the
          // right number of items if this query returns many duplicate IDs
          // that we've already selected.

          $realname_ids = queryfx_all(
            $conn_r,
            'SELECT DISTINCT userID FROM %T WHERE token LIKE %>
              ORDER BY token ASC LIMIT %d',
            PhabricatorUser::NAMETOKEN_TABLE,
            $query,
            $limit);
          $realname_ids = ipull($realname_ids, 'userID');
          $ids = array_merge($ids, $realname_ids);

          $ids = array_unique($ids);
          $ids = array_slice($ids, 0, $limit);
        }

        // Always add the logged-in user because some tokenizers autosort them
        // first. They'll be filtered out on the client side if they don't
        // match the query.
        $ids[] = $request->getUser()->getID();

        if ($ids) {
          $users = id(new PhabricatorUser())->loadColumnsWhere(
            $columns,
            'id IN (%Ld)',
            $ids);
        } else {
          $users = array();
        }
      } else {
        $users = id(new PhabricatorUser())->loadColumns($columns);
      }

      if ($need_rich_data) {
        $phids = mpull($users, 'getPHID');
        $handles = $this->loadViewerHandles($phids);
      }

      foreach ($users as $user) {
        if (!$need_all_users) {
          if ($user->getIsSystemAgent()) {
            continue;
          }
          if ($user->getIsDisabled()) {
            continue;
          }
        }

        $result = id(new PhabricatorTypeaheadResult())
          ->setName($user->getFullName())
          ->setURI('/p/'.$user->getUsername())
          ->setPHID($user->getPHID())
          ->setPriorityString($user->getUsername());

        if ($need_rich_data) {
          $display_type = 'User';
          if ($user->getIsAdmin()) {
            $display_type = 'Administrator';
          }
          $result->setDisplayType($display_type);
          $result->setImageURI($handles[$user->getPHID()]->getImageURI());
          $result->setPriorityType('user');
        }
        $results[] = $result;
      }
    }

    if ($need_lists) {
      $lists = id(new PhabricatorMetaMTAMailingList())->loadAll();
      foreach ($lists as $list) {
        $results[] = id(new PhabricatorTypeaheadResult())
          ->setName($list->getName())
          ->setURI($list->getURI())
          ->setPHID($list->getPHID());
      }
    }

    if ($need_projs) {
      $projs = id(new PhabricatorProject())->loadAllWhere(
        'status != %d',
        PhabricatorProjectStatus::STATUS_ARCHIVED);
      foreach ($projs as $proj) {
        $results[] = id(new PhabricatorTypeaheadResult())
          ->setName($proj->getName())
          ->setURI('/project/view/'.$proj->getID().'/')
          ->setPHID($proj->getPHID());
      }
    }

    if ($need_repos) {
      $repos = id(new PhabricatorRepository())->loadAll();
      foreach ($repos as $repo) {
        $results[] = id(new PhabricatorTypeaheadResult())
          ->setName('r'.$repo->getCallsign().' ('.$repo->getName().')')
          ->setURI('/diffusion/'.$repo->getCallsign().'/')
          ->setPHID($repo->getPHID())
          ->setPriorityString('r'.$repo->getCallsign());
      }
    }

    if ($need_packages) {
      $packages = id(new PhabricatorOwnersPackage())->loadAll();
      foreach ($packages as $package) {
        $results[] = id(new PhabricatorTypeaheadResult())
          ->setName($package->getName())
          ->setURI('/owners/package/'.$package->getID().'/')
          ->setPHID($package->getPHID());
      }
    }

    if ($need_arcanist_projects) {
      $arcprojs = id(new PhabricatorRepositoryArcanistProject())->loadAll();
      foreach ($arcprojs as $proj) {
        $results[] = id(new PhabricatorTypeaheadResult())
          ->setName($proj->getName())
          ->setPHID($proj->getPHID());
      }
    }

    if ($need_applications) {
      $applications = PhabricatorApplication::getAllInstalledApplications();
      foreach ($applications as $application) {
        $uri = $application->getTypeaheadURI();
        if (!$uri) {
          continue;
        }
        $name = $application->getName().' '.$application->getShortDescription();

        $results[] = id(new PhabricatorTypeaheadResult())
          ->setName($name)
          ->setURI($uri)
          ->setPHID($application->getPHID())
          ->setPriorityString($application->getName())
          ->setDisplayName($application->getName())
          ->setDisplayType($application->getShortDescription())
          ->setImageuRI($application->getIconURI())
          ->setPriorityType('apps');
      }
    }

    if ($need_symbols) {
      $symbols = id(new DiffusionSymbolQuery())
        ->setNamePrefix($query)
        ->setLimit(15)
        ->needArcanistProjects(true)
        ->needRepositories(true)
        ->needPaths(true)
        ->execute();
      foreach ($symbols as $symbol) {
        $lang = $symbol->getSymbolLanguage();
        $name = $symbol->getSymbolName();
        $type = $symbol->getSymbolType();
        $proj = $symbol->getArcanistProject()->getName();

        $results[] = id(new PhabricatorTypeaheadResult())
          ->setName($name)
          ->setURI($symbol->getURI())
          ->setPHID(md5($symbol->getURI())) // Just needs to be unique.
          ->setDisplayName($symbol->getName())
          ->setDisplayType(strtoupper($lang).' '.ucwords($type).' ('.$proj.')')
          ->setPriorityType('symb');
      }
    }

    $content = mpull($results, 'getWireFormat');

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())->setContent($content);
    }

    // If there's a non-Ajax request to this endpoint, show results in a tabular
    // format to make it easier to debug typeahead output.

    $rows = array();
    foreach ($results as $result) {
      $wire = $result->getWireFormat();
      foreach ($wire as $k => $v) {
        $wire[$k] = phutil_escape_html($v);
      }
      $rows[] = $wire;
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Name',
        'URI',
        'PHID',
        'Priority',
        'Display Name',
        'Display Type',
        'Image URI',
        'Priority Type',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Typeahead Results');
    $panel->appendChild($table);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Typeahead Results',
      ));
  }

}
