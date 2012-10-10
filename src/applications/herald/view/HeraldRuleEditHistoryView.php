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

final class HeraldRuleEditHistoryView extends AphrontView {

  private $edits;
  private $handles;
  private $user;

  public function setEdits(array $edits) {
    $this->edits = $edits;
    return $this;
  }

  public function getEdits() {
    return $this->edits;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function render() {
    $rows = array();

    foreach ($this->edits as $edit) {
      $name = nonempty($edit->getRuleName(), 'Unknown Rule');
      $rule_name = phutil_render_tag(
        'strong',
        array(),
        phutil_escape_html($name));

      switch ($edit->getAction()) {
        case 'create':
          $details = "Created rule '{$rule_name}'.";
          break;
        case 'delete':
          $details = "Deleted rule '{$rule_name}'.";
          break;
        case 'edit':
        default:
          $details = "Edited rule '{$rule_name}'.";
          break;
      }

      $rows[] = array(
        $edit->getRuleID(),
        $this->handles[$edit->getEditorPHID()]->renderLink(),
        $details,
        phabricator_datetime($edit->getDateCreated(), $this->user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setNoDataString("No edits for rule.");
    $table->setHeaders(
      array(
        'Rule ID',
        'Editor',
        'Details',
        'Edit Date',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide',
        '',
      ));

    return $table->render();
  }
}
