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
 * @group maniphest
 */
final class ManiphestSavedQueryListController extends ManiphestController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $nav = $this->buildBaseSideNav();

    $queries = id(new ManiphestSavedQuery())->loadAllWhere(
      'userPHID = %s ORDER BY name ASC',
      $user->getPHID());

    $default = null;

    if ($request->isFormPost()) {
      $new_default = null;
      foreach ($queries as $query) {
        if ($query->getID() == $request->getInt('default')) {
          $new_default = $query;
        }
      }

      if ($this->getDefaultQuery()) {
        $this->getDefaultQuery()->setIsDefault(0)->save();
      }
      if ($new_default) {
        $new_default->setIsDefault(1)->save();
      }

      return id(new AphrontRedirectResponse())->setURI('/maniphest/custom/');
    }

    $rows = array();
    foreach ($queries as $query) {
      if ($query->getIsDefault()) {
        $default = $query;
      }
      $rows[] = array(
        phutil_render_tag(
          'input',
          array(
            'type'      => 'radio',
            'name'      => 'default',
            'value'     => $query->getID(),
            'checked'   => ($query->getIsDefault() ? 'checked' : null),
          )),
        phutil_render_tag(
          'a',
          array(
            'href' => '/maniphest/view/custom/?key='.$query->getQueryKey(),
          ),
          phutil_escape_html($query->getName())),
        phutil_render_tag(
          'a',
          array(
            'href'  => '/maniphest/custom/edit/'.$query->getID().'/',
            'class' => 'grey small button',
          ),
          'Edit'),
        javelin_render_tag(
          'a',
          array(
            'href'  => '/maniphest/custom/delete/'.$query->getID().'/',
            'class' => 'grey small button',
            'sigil' => 'workflow',
          ),
          'Delete'),
      );
    }

    $rows[] = array(
      phutil_render_tag(
        'input',
        array(
          'type'      => 'radio',
          'name'      => 'default',
          'value'     => 0,
          'checked'   => ($default === null ? 'checked' : null),
        )),
      '<em>No Default</em>',
      '',
      '',
    );

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Default',
        'Name',
        'Edit',
        'Delete',
      ));
    $table->setColumnClasses(
      array(
        'radio',
        'wide pri',
        'action',
        'action',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Saved Custom Queries');
    $panel->addButton(
      phutil_render_tag(
        'button',
        array(),
        'Save Default Query'));
    $panel->appendChild($table);

    $form = phabricator_render_form(
      $user,
      array(
        'method' => 'POST',
        'action' => $request->getRequestURI(),
      ),
      $panel->render());

    $nav->selectFilter('saved', 'saved');
    $nav->appendChild($form);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Saved Queries',
      ));
  }

}
