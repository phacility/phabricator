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
  private $fields;
  const NO_DATA_STRING = 'No revisions found.';

  public function setFields(array $fields) {
    assert_instances_of($fields, 'DifferentialFieldSpecification');
    $this->fields = $fields;
    return $this;
  }

  public function setRevisions(array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');
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
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function render() {

    $user = $this->user;
    if (!$user) {
      throw new Exception("Call setUser() before render()!");
    }

    $flags = id(new PhabricatorFlagQuery())
      ->withOwnerPHIDs(array($user->getPHID()))
      ->withObjectPHIDs(mpull($this->revisions, 'getPHID'))
      ->execute();
    $flagged = mpull($flags, null, 'getObjectPHID');

    foreach ($this->fields as $field) {
      $field->setUser($this->user);
      $field->setHandles($this->handles);
    }

    $rows = array();
    foreach ($this->revisions as $revision) {
      $phid = $revision->getPHID();
      $flag = '';
      if (isset($flagged[$phid])) {
        $class = PhabricatorFlagColor::getCSSClass($flagged[$phid]->getColor());
        $note = $flagged[$phid]->getNote();
        $flag = phutil_render_tag(
          'div',
          array(
            'class' => 'phabricator-flag-icon '.$class,
            'title' => $note,
          ),
          '');
      }
      $row = array($flag);
      foreach ($this->fields as $field) {
        $row[] = $field->renderValueForRevisionList($revision);
      }
      $rows[] = $row;
    }

    $headers = array('');
    $classes = array('');
    foreach ($this->fields as $field) {
      $headers[] = $field->renderHeaderForRevisionList();
      $classes[] = $field->getColumnClassForRevisionList();
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders($headers);
    $table->setColumnClasses($classes);

    $table->setNoDataString(DifferentialRevisionListView::NO_DATA_STRING);

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
