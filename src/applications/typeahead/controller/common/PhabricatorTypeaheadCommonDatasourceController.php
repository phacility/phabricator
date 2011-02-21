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

    $need_users = false;
    $need_lists = false;
    $need_projs = false;
    $need_upforgrabs = false;
    switch ($this->type) {
      case 'searchowner':
        $need_users = true;
        $need_upforgrabs = true;
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
    }

    $data = array();

    if ($need_upforgrabs) {
      $data[] = array(
        'Up For Grabs',
        null,
        'PHID-!!!!-UP-FOR-GRABS',
      );
    }

    if ($need_users) {
      $users = id(new PhabricatorUser())->loadAll();
      foreach ($users as $user) {
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
          $list->getEmail(),
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

    return id(new AphrontAjaxResponse())
      ->setContent($data);
  }

}
