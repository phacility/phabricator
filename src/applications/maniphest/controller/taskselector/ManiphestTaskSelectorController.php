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

class ManiphestTaskSelectorController extends ManiphestController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $filter_id = celerity_generate_unique_node_id();
    $query_id = celerity_generate_unique_node_id();
    $search_id = celerity_generate_unique_node_id();
    $results_id = celerity_generate_unique_node_id();
    $current_id = celerity_generate_unique_node_id();

    $search_box =
      '<table class="phabricator-object-selector-search">
        <tr>
          <td class="phabricator-object-selector-search-filter">
            <select id="'.$filter_id.'">
              <option>Assigned To Me</option>
              <option>Created By Me</option>
              <option>All Open Tasks</option>
              <option>All Tasks</option>
            </select>
          </td>
          <td class="phabricator-object-selector-search-text">
            <input type="text" id="'.$query_id.'" />
          </td>
          <td class="phabricator-object-selector-search-button">
            <a href="#" class="button" id="'.$search_id.'">Search</a>
          </td>
        </tr>
      </table>';
    $result_box =
      '<div class="phabricator-object-selector-results" id="'.$results_id.'">'.

      '</div>';
    $attached_box =
      '<div class="phabricator-object-selector-current">'.
        '<div class="phabricator-object-selector-currently-attached">'.
          '<div class="phabricator-object-selector-header">'.
            'Currently Attached Tasks'.
          '</div>'.
          '<div id="'.$current_id.'">'.
          '</div>'.
        '</div>'.
      '</div>';

    require_celerity_resource('phabricator-object-selector-css');

    Javelin::initBehavior(
      'phabricator-object-selector',
      array(
        'filter'  => $filter_id,
        'query'   => $query_id,
        'search'  => $search_id,
        'results' => $results_id,
        'current' => $current_id,
        'uri'     => '/maniphest/select/search/',
      ));

    $dialog = new PhabricatorObjectSelectorDialog();
    $dialog
      ->setUser($user)
      ->setTitle('Manage Attached Tasks')
      ->setClass('phabricator-object-selector-dialog')
      ->appendChild($search_box)
      ->appendChild($result_box)
      ->appendChild($attached_box)
      ->addCancelButton('#')
      ->addSubmitButton('Save Tasks');

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}

/*

        '<table class="phabricator-object-selector-handle">
          <tr>
            <th>
              <input type="checkbox" />
            </th>
            <td>
              <a href="#">T20: Internet Attack Internets</a>
            </td>
          </tr>
        </table>'.
        '<table class="phabricator-object-selector-handle">
          <tr>
            <th>
              <input type="checkbox" />
            </th>
            <td>
              <a href="#">T21: Internet Attack Internets</a>
            </td>
          </tr>
        </table>'.
        '<table class="phabricator-object-selector-handle">
          <tr>
            <th>
              <input type="checkbox" />
            </th>
            <td>
              <a href="#">T22: Internet Attack Internets</a>
            </td>
          </tr>
        </table>'.
        'more results<br />'.
        'more results<br />'.
        'more results<br />'.
        'more results<br />'.
        'more results<br />'.
        'more results<br />'.
        'more results<br />'.
        'more results<br />'.
        'more results<br />'.
        'more results<br />'.

*/


/*

          '<table class="phabricator-object-selector-handle">
            <tr>
              <th>
                <input type="checkbox" />
              </th>
              <td>
                <a href="#">T22: Internet Attack Internets</a>
              </td>
            </tr>
          </table>'.
          '<table class="phabricator-object-selector-handle">
            <tr>
              <th>
                <input type="checkbox" />
              </th>
              <td>
                <a href="#">T22: Internet Attack Internets</a>
              </td>
            </tr>
          </table>'.

*/
