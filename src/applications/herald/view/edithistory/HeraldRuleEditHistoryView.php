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
  private $rule;
  private $handles;

  public function setRule($rule) {
    $this->rule = $rule;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function render() {
    $rows = array();

    foreach ($this->rule->getEdits() as $edit) {
      $editor = $this->handles[$edit->getEditorPHID()]->renderLink();

      $edit_date = phabricator_datetime($edit->getDateCreated(), $this->user);

      // Something useful could totally go here
      $details = "";

      $rows[] = array(
        $editor,
        $edit_date,
        $details,
      );
    }

    $rule_name = phutil_escape_html($this->rule->getName());
    $table = new AphrontTableView($rows);
    $table->setNoDataString(
      "No edits for \"${rule_name}\"");
    $table->setHeaders(
      array(
        'Editor',
        'Edit Date',
        'Details',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader("Edit History for \"${rule_name}\"");
    $panel->appendChild($table);

    return $panel;
  }
}
