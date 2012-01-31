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

final class HeraldRuleListView extends AphrontView {
  private $rules;
  private $handles;
  private $map;
  private $view;
  private $allowCreation;
  private $showOwner = true;
  private $showType = false;
  private $user;

  public function setRules(array $rules) {
    $this->rules = $rules;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setMap($map) {
    $this->map = $map;
    return $this;
  }

  public function setView($view) {
    $this->view = $view;
    return $this;
  }

  public function setAllowCreation($allow_creation) {
    $this->allowCreation = $allow_creation;
    return $this;
  }

  public function setShowOwner($show_owner) {
    $this->showOwner = $show_owner;
    return $this;
  }

  public function setShowType($show_type) {
    $this->showType = $show_type;
    return $this;
  }

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function render() {

    $type_map = HeraldRuleTypeConfig::getRuleTypeMap();

    $rows = array();

    foreach ($this->rules as $rule) {
      $owner = $this->handles[$rule->getAuthorPHID()]->renderLink();

      $name = phutil_render_tag(
        'a',
        array(
          'href' => '/herald/rule/'.$rule->getID().'/',
        ),
        phutil_escape_html($rule->getName()));

      $last_edit_date = phabricator_datetime($rule->getDateModified(),
                                             $this->user);

      $view_edits = phutil_render_tag(
        'a',
        array(
          'href' => '/herald/history/' . $rule->getID() . '/',
        ),
        '(View Edits)');

      $last_edited = phutil_render_tag(
        'span',
        array(),
        "Last edited on $last_edit_date ${view_edits}");


      $delete = javelin_render_tag(
        'a',
        array(
          'href' => '/herald/delete/'.$rule->getID().'/',
          'sigil' => 'workflow',
          'class' => 'button small grey',
        ),
        'Delete');

      $rows[] = array(
        $this->map[$rule->getContentType()],
        $type_map[$rule->getRuleType()],
        $owner,
        $name,
        $last_edited,
        $delete,
      );
    }

    $rules_for = phutil_escape_html($this->map[$this->view]);

    $table = new AphrontTableView($rows);
    $table->setNoDataString(
      "No matching subscription rules for {$rules_for}.");

    $table->setHeaders(
      array(
        'Content Type',
        'Rule Type',
        'Owner',
        'Rule Name',
        'Last Edited',
        '',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        '',
        'wide wrap pri',
        '',
        'action'
      ));
    $table->setColumnVisibility(
      array(
        true,
        $this->showType,
        $this->showOwner,
        true,
        true,
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader("Herald Rules for {$rules_for}");

    if ($this->allowCreation) {
      $panel->setCreateButton(
        'Create New Herald Rule',
        '/herald/new/'.$this->view.'/');
    }

    $panel->appendChild($table);

    return $panel;

  }
}
