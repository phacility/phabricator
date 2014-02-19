<?php

final class PhabricatorTypeaheadCommonDatasourceController
  extends PhabricatorTypeaheadDatasourceController {

  private $type;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->type = $data['type'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $query = $request->getStr('q');
    $raw_query = $request->getStr('raw');

    $need_rich_data = false;

    $need_users = false;
    $need_agents = false;
    $need_applications = false;
    $need_lists = false;
    $need_projs = false;
    $need_repos = false;
    $need_packages = false;
    $need_upforgrabs = false;
    $need_arcanist_projects = false;
    $need_noproject = false;
    $need_symbols = false;
    $need_jump_objects = false;
    $need_build_plans = false;
    $need_task_priority = false;
    $need_macros = false;
    $need_legalpad_documents = false;
    switch ($this->type) {
      case 'mainsearch':
        $need_users = true;
        $need_applications = true;
        $need_rich_data = true;
        $need_symbols = true;
        $need_projs = true;
        $need_jump_objects = true;
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
      case 'accounts':
      case 'authors':
        $need_users = true;
        break;
      case 'mailable':
      case 'allmailable':
        $need_users = true;
        $need_lists = true;
        $need_projs = true;
        break;
      case 'projects':
        $need_projs = true;
        break;
      case 'usersorprojects':
      case 'accountsorprojects':
        $need_users = true;
        $need_projs = true;
        break;
      case 'repositories':
        $need_repos = true;
        break;
      case 'packages':
        $need_packages = true;
        break;
      case 'arcanistprojects':
        $need_arcanist_projects = true;
        break;
      case 'buildplans':
        $need_build_plans = true;
        break;
      case 'taskpriority':
        $need_task_priority = true;
        break;
      case 'macros':
        $need_macros = true;
        break;
      case 'legalpaddocuments':
        $need_legalpad_documents = true;
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
        $closed = null;
        if ($user->getIsDisabled()) {
          $closed = pht('Disabled');
        } else if ($user->getIsSystemAgent()) {
          $closed = pht('System Agent');
        }

        $result = id(new PhabricatorTypeaheadResult())
          ->setName($user->getFullName())
          ->setURI('/p/'.$user->getUsername())
          ->setPHID($user->getPHID())
          ->setPriorityString($user->getUsername())
          ->setIcon('policy-all')
          ->setPriorityType('user')
          ->setClosed($closed);

        if ($need_rich_data) {
          $display_type = 'User';
          if ($user->getIsAdmin()) {
            $display_type = 'Administrator';
          }
          $result->setDisplayType($display_type);
          $result->setImageURI($handles[$user->getPHID()]->getImageURI());
        }
        $results[] = $result;
      }
    }

    if ($need_lists) {
      $lists = id(new PhabricatorMailingListQuery())
        ->setViewer($viewer)
        ->execute();
      foreach ($lists as $list) {
        $results[] = id(new PhabricatorTypeaheadResult())
          ->setName($list->getName())
          ->setURI($list->getURI())
          ->setPHID($list->getPHID());
      }
    }

    if ($need_build_plans) {
      $plans = id(new HarbormasterBuildPlanQuery())
        ->setViewer($viewer)
        ->execute();
      foreach ($plans as $plan) {
        $results[] = id(new PhabricatorTypeaheadResult())
          ->setName($plan->getName())
          ->setPHID($plan->getPHID());
      }
    }

    if ($need_task_priority) {
      $priority_map = ManiphestTaskPriority::getTaskPriorityMap();
      foreach ($priority_map as $value => $name) {
        // NOTE: $value is not a phid but is unique. This'll work.
        $results[] = id(new PhabricatorTypeaheadResult())
          ->setPHID($value)
          ->setName($name);
      }
    }

    if ($need_macros) {
      $macros = id(new PhabricatorMacroQuery())
        ->setViewer($viewer)
        ->withStatus(PhabricatorMacroQuery::STATUS_ACTIVE)
        ->execute();
      $macros = mpull($macros, 'getName', 'getPHID');
      foreach ($macros as $phid => $name) {
        $results[] = id(new PhabricatorTypeaheadResult())
          ->setPHID($phid)
          ->setName($name);
      }
    }

    if ($need_legalpad_documents) {
      $documents = id(new LegalpadDocumentQuery())
        ->setViewer($viewer)
        ->execute();
      $documents = mpull($documents, 'getTitle', 'getPHID');
      foreach ($documents as $phid => $title) {
        $results[] = id(new PhabricatorTypeaheadResult())
          ->setPHID($phid)
          ->setName($title);
      }
    }

    if ($need_projs) {
      $projs = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->needImages(true)
        ->execute();
      foreach ($projs as $proj) {
        $closed = null;
        if ($proj->isArchived()) {
          $closed = pht('Archived');
        }

        $proj_result = id(new PhabricatorTypeaheadResult())
          ->setName($proj->getName())
          ->setDisplayType("Project")
          ->setURI('/project/view/'.$proj->getID().'/')
          ->setPHID($proj->getPHID())
          ->setIcon('policy-project')
          ->setClosed($closed);

        $proj_result->setImageURI($proj->getProfileImageURI());

        $results[] = $proj_result;
      }
    }

    if ($need_repos) {
      $repos = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->execute();
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
          ->setDisplayName($name)
          ->setDisplayType(strtoupper($lang).' '.ucwords($type).' ('.$proj.')')
          ->setPriorityType('symb');
      }
    }

    if ($need_jump_objects) {
      $objects = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withNames(array($raw_query))
        ->execute();
      if ($objects) {
        $handles = id(new PhabricatorHandleQuery())
          ->setViewer($viewer)
          ->withPHIDs(mpull($objects, 'getPHID'))
          ->execute();
        $handle = head($handles);
        if ($handle) {
          $results[] = id(new PhabricatorTypeaheadResult())
            ->setName($handle->getFullName())
            ->setDisplayType($handle->getTypeName())
            ->setURI($handle->getURI())
            ->setPHID($handle->getPHID())
            ->setPriorityType('jump');
        }
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
