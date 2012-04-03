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

  private $showAuthor;
  private $showRuleType;
  private $user;

  public function setRules(array $rules) {
    assert_instances_of($rules, 'HeraldRule');
    $this->rules = $rules;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setShowAuthor($show_author) {
    $this->showAuthor = $show_author;
    return $this;
  }

  public function setShowRuleType($show_rule_type) {
    $this->showRuleType = $show_rule_type;
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

      if ($rule->getRuleType() == HeraldRuleTypeConfig::RULE_TYPE_GLOBAL) {
        $author = null;
      } else {
        $author = $this->handles[$rule->getAuthorPHID()]->renderLink();
      }

      $name = phutil_render_tag(
        'a',
        array(
          'href' => '/herald/rule/'.$rule->getID().'/',
        ),
        phutil_escape_html($rule->getName()));

      $edit_log = phutil_render_tag(
        'a',
        array(
          'href' => '/herald/history/'.$rule->getID().'/',
        ),
        'View Edit Log');

      $delete = javelin_render_tag(
        'a',
        array(
          'href' => '/herald/delete/'.$rule->getID().'/',
          'sigil' => 'workflow',
          'class' => 'button small grey',
        ),
        'Delete');

      $rows[] = array(
        $type_map[$rule->getRuleType()],
        $author,
        $name,
        $edit_log,
        $delete,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setNoDataString("No matching rules.");

    $table->setHeaders(
      array(
        'Rule Type',
        'Author',
        'Rule Name',
        'Edit Log',
        '',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide pri',
        '',
        'action'
      ));
    $table->setColumnVisibility(
      array(
        $this->showRuleType,
        $this->showAuthor,
        true,
        true,
        true,
      ));

    return $table->render();
  }
}
