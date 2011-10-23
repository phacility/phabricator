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

class PhabricatorTypeaheadCommonDatasourceController
  extends PhabricatorTypeaheadDatasourceController {

  public function willProcessRequest(array $data) {
    $this->type = $data['type'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $query = $request->getStr('q');

    $need_users = false;
    $need_all_users = false;
    $need_lists = false;
    $need_projs = false;
    $need_repos = false;
    $need_packages = false;
    $need_upforgrabs = false;
    $need_arcanist_projects = false;
    switch ($this->type) {
      case 'searchowner':
        $need_users = true;
        $need_upforgrabs = true;
        break;
      case 'users':
        $need_users = true;
        break;
      case 'mailable':
        $need_users = true;
        $need_lists = true;
        break;
      case 'projects':
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

    $data = array();

    if ($need_upforgrabs) {
      $data[] = array(
        'upforgrabs (Up For Grabs)',
        null,
        ManiphestTaskOwner::OWNER_UP_FOR_GRABS,
      );
    }

    if ($need_users) {
      $columns = array(
        'isSystemAgent',
        'isDisabled',
        'userName',
        'realName',
        'phid');
      if ($query) {
        $conn_r = id(new PhabricatorUser())->establishConnection('r');
        $ids = queryfx_all(
          $conn_r,
          'SELECT DISTINCT userID FROM %T WHERE token LIKE %>',
          PhabricatorUser::NAMETOKEN_TABLE,
          $query);
        $ids = ipull($ids, 'userID');
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
      foreach ($users as $user) {
        if (!$need_all_users) {
          if ($user->getIsSystemAgent()) {
            continue;
          }
          if ($user->getIsDisabled()) {
            continue;
          }
        }
        $data[] = array(
          $user->getUsername().' ('.$user->getRealName().')',
          '/p/'.$user->getUsername(),
          $user->getPHID(),
        );
      }
    }

    if ($need_lists) {
      $lists = id(new PhabricatorMetaMTAMailingList())->loadAll();
      foreach ($lists as $list) {
        $data[] = array(
          $list->getName(),
          $list->getURI(),
          $list->getPHID(),
        );
      }
    }

    if ($need_projs) {
      $projs = id(new PhabricatorProject())->loadAll();
      foreach ($projs as $proj) {
        $data[] = array(
          $proj->getName(),
          '/project/view/'.$proj->getID().'/',
          $proj->getPHID(),
        );
      }
    }

    if ($need_repos) {
      $repos = id(new PhabricatorRepository())->loadAll();
      foreach ($repos as $repo) {
        $data[] = array(
          'r'.$repo->getCallsign().' ('.$repo->getName().')',
          '/diffusion/'.$repo->getCallsign().'/',
          $repo->getPHID(),
        );
      }
    }

    if ($need_packages) {
      $packages = id(new PhabricatorOwnersPackage())->loadAll();
      foreach ($packages as $package) {
        $data[] = array(
          $package->getName(),
          '/owners/package/'.$package->getID().'/',
          $package->getPHID(),
        );
      }
    }

    if ($need_arcanist_projects) {
      $arcprojs = id(new PhabricatorRepositoryArcanistProject())->loadAll();
      foreach ($arcprojs as $proj) {
        $data[] = array(
          $proj->getName(),
          null,
          $proj->getPHID(),
        );
      }
    }

    return id(new AphrontAjaxResponse())
      ->setContent($data);
  }

}
