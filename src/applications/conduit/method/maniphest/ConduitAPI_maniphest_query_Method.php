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

/**
 * @group conduit
 *
 * TODO: Remove maniphest.find, then make this final.
 *
 * @concrete-extensible
 */
class ConduitAPI_maniphest_query_Method
  extends ConduitAPI_maniphest_Method {


  public function getMethodDescription() {
    return "Execute complex searches for Maniphest tasks.";
  }

  public function defineParamTypes() {

    $orders = array(
      ManiphestTaskQuery::ORDER_PRIORITY,
      ManiphestTaskQuery::ORDER_CREATED,
      ManiphestTaskQuery::ORDER_MODIFIED,
    );
    $orders = implode(', ', $orders);

    $statuses = array(
      ManiphestTaskQuery::STATUS_ANY,
      ManiphestTaskQuery::STATUS_OPEN,
      ManiphestTaskQuery::STATUS_CLOSED,
      ManiphestTaskQuery::STATUS_RESOLVED,
      ManiphestTaskQuery::STATUS_WONTFIX,
      ManiphestTaskQuery::STATUS_INVALID,
      ManiphestTaskQuery::STATUS_SPITE,
      ManiphestTaskQuery::STATUS_DUPLICATE,
    );
    $statuses = implode(', ', $statuses);

    return array(
      'ownerPHIDs'        => 'optional list',
      'authorPHIDs'       => 'optional list',
      'projectPHIDs'      => 'optional list',
      'ccPHIDs'           => 'optional list',

      'order'             => 'optional enum<'.$orders.'>',
      'status'            => 'optional enum<'.$statuses.'>',

      'limit'             => 'optional int',
      'offset'            => 'optional int',
    );
  }

  public function defineReturnType() {
    return 'list';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = new ManiphestTaskQuery();

    $owners = $request->getValue('ownerPHIDs');
    if ($owners) {
      $query->withOwners($owners);
    }

    $authors = $request->getValue('authorPHIDs');
    if ($authors) {
      $query->withAuthors($authors);
    }

    $projects = $request->getValue('projectPHIDs');
    if ($projects) {
      $query->withProjects($projects);
    }

    $ccs = $request->getValue('ccPHIDs');
    if ($ccs) {
      $query->withSubscribers($ccs);
    }

    $order = $request->getValue('order');
    if ($order) {
      $query->setOrderBy($order);
    }

    $status = $request->getValue('status');
    if ($status) {
      $query->withStatus($status);
    }

    $limit = $request->getValue('limit');
    if ($limit) {
      $query->setLimit($limit);
    }

    $offset = $request->getValue('offset');
    if ($offset) {
      $query->setOffset($offset);
    }

    $results = $query->execute();
    return $this->buildTaskInfoDictionaries($results);
  }

}
