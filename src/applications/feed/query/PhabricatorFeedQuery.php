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

final class PhabricatorFeedQuery {

  private $filterPHIDs;
  private $limit = 100;
  private $after;
  private $before;

  public function setFilterPHIDs(array $phids) {
    $this->filterPHIDs = $phids;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function setAfter($after) {
    $this->after = $after;
    return $this;
  }

  public function setBefore($before) {
    $this->before = $before;
    return $this;
  }

  public function execute() {

    $ref_table = new PhabricatorFeedStoryReference();
    $story_table = new PhabricatorFeedStoryData();

    $conn = $story_table->establishConnection('r');

    $where = array();
    if ($this->filterPHIDs) {
      $where[] = qsprintf(
        $conn,
        'ref.objectPHID IN (%Ls)',
        $this->filterPHIDs);
    }

    // For "before" queries, we can just add a constraint to the WHERE clause.
    // For "after" queries, we must also reverse the result ordering, since
    // otherwise we'll always grab the first page of results if there's a limit.
    // After MySQL applies the limit, we reverse the page in PHP (below) to
    // ensure consistent ordering.

    $order = 'DESC';

    if ($this->after) {
      $where[] = qsprintf(
        $conn,
        'ref.chronologicalKey > %s',
        $this->after);
      $order = 'ASC';
    }

    if ($this->before) {
      $where[] = qsprintf(
        $conn,
        'ref.chronologicalKey < %s',
        $this->before);
    }

    if ($where) {
      $where = 'WHERE ('.implode(') AND (', $where).')';
    } else {
      $where = '';
    }

    $data = queryfx_all(
      $conn,
      'SELECT story.* FROM %T ref
        JOIN %T story ON ref.chronologicalKey = story.chronologicalKey
        %Q
        GROUP BY ref.chronologicalKey
        ORDER BY ref.chronologicalKey %Q
        LIMIT %d',
      $ref_table->getTableName(),
      $story_table->getTableName(),
      $where,
      $order,
      $this->limit);

    if ($order != 'DESC') {
      // If we did order ASC to pull 'after' data, reverse the result set so
      // that stories are returned in a consistent (descending) order.
      $data = array_reverse($data);
    }

    $data = $story_table->loadAllFromArray($data);

    $stories = array();
    foreach ($data as $story_data) {
      $class = $story_data->getStoryType();

      try {
        if (!class_exists($class) ||
            !is_subclass_of($class, 'PhabricatorFeedStory')) {
          $class = 'PhabricatorFeedStoryUnknown';
        }
      } catch (PhutilMissingSymbolException $ex) {
        // If the class can't be loaded, libphutil will throw an exception.
        // Render the story using the unknown story view.
        $class = 'PhabricatorFeedStoryUnknown';
      }

      $stories[] = newv($class, array($story_data));
    }

    return $stories;
  }
}
