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

final class DifferentialRevisionDetailView extends AphrontView {

  private $revision;
  private $actions;
  private $user;
  private $auxiliaryFields = array();

  public function setRevision($revision) {
    $this->revision = $revision;
    return $this;
  }

  public function setActions(array $actions) {
    $this->actions = $actions;
    return $this;
  }

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function setAuxiliaryFields(array $fields) {
    $this->auxiliaryFields = $fields;
    return $this;
  }

  public function render() {

    require_celerity_resource('differential-core-view-css');
    require_celerity_resource('differential-revision-detail-css');

    $revision = $this->revision;

    $rows = array();
    foreach ($this->auxiliaryFields as $field) {
      $value = $field->renderValueForRevisionView();
      if (strlen($value)) {
        $label = $field->renderLabelForRevisionView();
        $rows[] =
          '<tr>'.
            '<th>'.$label.'</th>'.
            '<td>'.$value.'</td>'.
          '</tr>';
      }
    }

    $properties =
      '<table class="differential-revision-properties">'.
        implode("\n", $rows).
      '</table>';

    $actions = array();
    foreach ($this->actions as $action) {
      $obj = new AphrontHeadsupActionView();
      $obj->setName($action['name']);
      $obj->setURI(idx($action, 'href'));
      $obj->setWorkflow(idx($action, 'sigil') == 'workflow');
      $obj->setClass(idx($action, 'class'));
      $obj->setInstant(idx($action, 'instant'));
      $obj->setUser($this->user);
      $actions[] = $obj;
    }

    $action_list = new AphrontHeadsupActionListView();
    $action_list->setActions($actions);

    return
      '<div class="differential-revision-detail differential-panel">'.
        $action_list->render().
        '<div class="differential-keyboard-shortcuts">'.
          id(new AphrontKeyboardShortcutsAvailableView())->render().
        '</div>'.
        '<div class="differential-revision-detail-core">'.
          '<h1>'.
            '<span class="aphront-headsup-object-name">'.
              phutil_escape_html('D'.$revision->getID()).
            '</span>'.
            ' '.
            phutil_escape_html($revision->getTitle()).'</h1>'.
          $properties.
        '</div>'.
        '<div style="clear: both;"></div>'.
      '</div>';
  }
}
