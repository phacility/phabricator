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

final class HeraldRuleListView extends AphrontView {
  private $rules;
  private $handles;
  private $map;
  private $view;
  private $allowCreation;

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

  public function render() {
    $rows = array();
    foreach ($this->rules as $rule) {
      $owner = $this->handles[$rule->getAuthorPHID()]->renderLink();

      $name = phutil_render_tag(
        'a',
        array(
          'href' => '/herald/rule/'.$rule->getID().'/',
        ),
        phutil_escape_html($rule->getName()));

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
        $owner,
        $name,
        $delete,
      );
    }

    $rules_for = phutil_escape_html($this->map[$this->view]);

    $table = new AphrontTableView($rows);
    $table->setNoDataString(
      "No matching subscription rules for {$rules_for}.");

    $table->setHeaders(
      array(
        'Type',
        'Owner',
        'Rule Name',
        '',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide wrap pri',
        'action'
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
