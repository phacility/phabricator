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

final class PhabricatorFlagListView extends AphrontView {

  private $flags;
  private $user;

  public function setFlags(array $flags) {
    assert_instances_of($flags, 'PhabricatorFlag');
    $this->flags = $flags;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function render() {
    $user = $this->user;

    require_celerity_resource('phabricator-flag-css');

    $rows = array();
    foreach ($this->flags as $flag) {
      $class = PhabricatorFlagColor::getCSSClass($flag->getColor());

      $rows[] = array(
        phutil_render_tag(
          'div',
          array(
            'class' => 'phabricator-flag-icon '.$class,
          ),
          ''),
        $flag->getHandle()->renderLink(),
        phutil_escape_html($flag->getNote()),
        phabricator_datetime($flag->getDateCreated(), $user),
        phabricator_render_form(
          $user,
          array(
            'method' => 'POST',
            'action' => '/flag/edit/'.$flag->getObjectPHID().'/',
            'sigil'  => 'workflow',
          ),
          phutil_render_tag(
            'button',
            array(
              'class' => 'small grey',
            ),
            'Edit Flag')),
        phabricator_render_form(
          $user,
          array(
            'method' => 'POST',
            'action' => '/flag/delete/'.$flag->getID().'/',
            'sigil'  => 'workflow',
          ),
          phutil_render_tag(
            'button',
            array(
              'class' => 'small grey',
            ),
            'Remove Flag')),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        '',
        'Flagged Object',
        'Note',
        'Flagged On',
        '',
        '',
      ));
    $table->setColumnClasses(
      array(
        'narrow',
        'wrap pri',
        'wrap',
        'narrow',
        'narrow action',
        'narrow action',
      ));
    $table->setNoDataString('No flags.');

    return $table->render();
  }
}
