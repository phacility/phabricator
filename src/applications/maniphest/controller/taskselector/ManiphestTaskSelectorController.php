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

class ManiphestTaskSelectorController extends ManiphestController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $phids = $request->getArr('phids');

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $obj_dialog = new PhabricatorObjectSelectorDialog();
    $obj_dialog
      ->setUser($user)
      ->setHandles($handles)
      ->setFilters(array(
        'assigned' => 'Assigned to Me',
        'created'  => 'Created By Me',
        'open'     => 'All Open Tasks',
        'all'      => 'All Tasks',
      ))
      ->setCancelURI('#')
      ->setSearchURI('/maniphest/select/search/')
      ->setNoun('Tasks');

    $dialog = $obj_dialog->buildDialog();


    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
