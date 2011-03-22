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

class HeraldHomeController extends HeraldController {

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $map = HeraldContentTypeConfig::getContentTypeMap();
    if (empty($map[$this->view])) {
      reset($map);
      $this->view = key($map);
    }

    $rules = id(new HeraldRule())->loadAllWhere(
      'contentType = %s AND authorPHID = %s',
      $this->view,
      $user->getPHID());

    $handles = array();
    $need_phids = mpull($rules, 'getAuthorPHID');

/*
    $data = new ToolsHandleData($need_fbids, $handles);
    $data->needNames();
    $data->needAlternateNames();
    prep($data);
*/
    $type = 'differential';

    $rows = array();
    foreach ($rules as $rule) {

      $owner = 'owner';
/*        <a href={$handles[$rule->getOwnerID()]->getURI()}>
          {$handles[$rule->getOwnerID()]->getName()}
        </a>;
*/
      $name = 'name';
/*        <a href={"/herald/rule/".$rule->getID()."/"}>
          {$rule->getName()}
        </a>;
*/
      $delete = 'delete';

/*        <a href={"/herald/delete/".$rule->getID()."/"} workflow={true}>
          Delete
        </a>;
*/
      $rows[] = array(
        $map[$rule->getContentType()],
        $owner->toString(),
        $name->toString(),
        $delete->toString(),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setNoDataString(
      "No matching subscription rules for ".
      phutil_escape_html($map[$this->view]).".");

    $table->setHeaders(
      array(
        'Type',
        'Owner',
        'Rule Name',
        'Delete',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide wrap',
        'action'
      ));

    $items = array();
    foreach ($map as $key => $value) {
      $uri = '/herald/view/'.$key.'/';
      $items[] = 'item';
/*        <tools:nav-item href={$uri} selected={$key == $this->view}>
          {$value}
        </tools:nav-item>;
*/
    }


//    require_static('herald-css');

    // If you're viewing as an admin, this string renders in the table header.
//    $map['admin'] = 'Omniscience';

    $sidenav = new AphrontSideNavView();
    foreach ($map as $key => $value) {
      $sidenav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => '/herald/view/'.$key.'/',
            'class' => ($key == $this->view)
              ? 'aphront-side-nav-selected'
              : null,
          ),
          phutil_escape_html($value)));
    }


    $content = array();
    $content[] = 'oh hi';

    return $this->buildStandardPageResponse(
      $sidenav,
      array(
        'title' => 'Herald',
      ));

/*
      <herald:standard-page title="Herald"
        selectednav={:herald:standard-page::NAV_RULES}>
        <div style="padding: 1em;">
          <tools:side-nav items={$items}>
            <div class="tools-table">
              <a href={"/herald/rule/?type=".$this->view}
                class="button green"
                style="float: right;">Create New Rule</a>
              <h1>Herald Subscription Rules
                for {txt2html($map[$this->view])}</h1>
              {HTML($table->render())}
            </div>
          </tools:side-nav>
        </div>
      </herald:standard-page>;
*/

  }

}
