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
 * Render a table of Differential revisions.
 */
final class DifferentialRevisionListView extends AphrontView {

  private $revisions;
  private $handles;
  private $user;
  private $noDataString;
  private $fields;

  public function setFields(array $fields) {
    $this->fields = $fields;
    return $this;
  }

  public function setRevisions(array $revisions) {
    $this->revisions = $revisions;
    return $this;
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    foreach ($this->fields as $field) {
      foreach ($this->revisions as $revision) {
        $phids[] = $field->getRequiredHandlePHIDsForRevisionList($revision);
      }
    }
    return array_mergev($phids);
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function render() {

    $user = $this->user;
    if (!$user) {
      throw new Exception("Call setUser() before render()!");
    }

    foreach ($this->fields as $field) {
      $field->setUser($this->user);
      $field->setHandles($this->handles);
    }

    $rows = array();
    foreach ($this->revisions as $revision) {
      $row = array();
      foreach ($this->fields as $field) {
        $row[] = $field->renderValueForRevisionList($revision);
      }
      $rows[] = $row;
    }

    $headers = array();
    $classes = array();
    foreach ($this->fields as $field) {
      $headers[] = $field->renderHeaderForRevisionList();
      $classes[] = $field->getColumnClassForRevisionList();
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders($headers);
    $table->setColumnClasses($classes);

    if ($this->noDataString) {
      $table->setNoDataString($this->noDataString);
    }

    return $table->render();
  }

  public static function getDefaultFields() {
    $selector = DifferentialFieldSelector::newSelector();
    $fields = $selector->getFieldSpecifications();
    foreach ($fields as $key => $field) {
      if (!$field->shouldAppearOnRevisionList()) {
        unset($fields[$key]);
      }
    }

    if (!$fields) {
      throw new Exception(
        "Phabricator configuration has no fields that appear on the list ".
        "interface!");
    }

    return $selector->sortFieldsForRevisionList($fields);
  }

}
