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
    switch ($this->type) {
      case 'users':
        $need_users = true;
        break;
      case 'mailable':
        $need_users = true;
        $need_lists = true;
        break;
    }

    $data = array();


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

    return id(new AphrontAjaxResponse())
      ->setContent($data);
  }

}
