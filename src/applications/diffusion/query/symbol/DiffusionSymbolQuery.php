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

final class DiffusionSymbolQuery {

  private $namePrefix;
  private $name;

  private $projectIDs;
  private $language;
  private $type;

  private $limit = 20;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function setNamePrefix($name_prefix) {
    $this->namePrefix = $name_prefix;
    return $this;
  }

  public function setProjectIDs(array $project_ids) {
    $this->projectIDs = $project_ids;
    return $this;
  }

  public function setLanguage($language) {
    $this->language = $language;
    return $this;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function execute() {
    if ($this->name && $this->namePrefix) {
      throw new Exception(
        "You can not set both a name and a name prefix!");
    } else if (!$this->name && !$this->namePrefix) {
      throw new Exception(
        "You must set a name or a name prefix!");
    }

    $symbol = new PhabricatorRepositorySymbol();
    $conn_r = $symbol->establishConnection('r');

    $where = array();
    if ($this->name) {
      $where[] = qsprintf(
        $conn_r,
        'symbolName = %s',
        $this->name);
    }

    if ($this->namePrefix) {
      $where[] = qsprintf(
        $conn_r,
        'symbolName LIKE %>',
        $this->namePrefix);
    }

    if ($this->projectIDs) {
      $where[] = qsprintf(
        $conn_r,
        'arcanistProjectID IN (%Ld)',
        $this->projectIDs);
    }

    $where = 'WHERE ('.implode(') AND (', $where).')';

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q',
      $symbol->getTableName(),
      $where);

    // Our ability to match up symbol types and languages probably isn't all
    // that great, so use them as hints for ranking rather than hard
    // requirements. TODO: Is this really the right choice?
    foreach ($data as $key => $row) {
      $score = 0;
      if ($this->language && $row['symbolLanguage'] == $this->language) {
        $score += 2;
      }
      if ($this->type && $row['symbolType'] == $this->type) {
        $score += 1;
      }
      $data[$key]['score'] = $score;
      $data[$key]['id'] = $key;
    }

    $data = isort($data, 'score');
    $data = array_reverse($data);

    $data = array_slice($data, 0, $this->limit);

    return $symbol->loadAllFromArray($data);
  }
}
