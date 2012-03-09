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

final class DiffusionHistoryController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->diffusionRequest;
    $request = $this->getRequest();

    $page_size = $request->getInt('pagesize', 100);
    $offset = $request->getInt('page', 0);

    $history_query = DiffusionHistoryQuery::newFromDiffusionRequest(
      $drequest);
    $history_query->setOffset($offset);
    $history_query->setLimit($page_size + 1);

    if (!$request->getBool('copies')) {
      $history_query->needDirectChanges(true);
      $history_query->needChildChanges(true);
    }

    $history = $history_query->loadHistory();

    $phids = array();
    foreach ($history as $item) {
      $data = $item->getCommitData();
      if ($data) {
        if ($data->getCommitDetail('authorPHID')) {
          $phids[$data->getCommitDetail('authorPHID')] = true;
        }
      }
    }
    $phids = array_keys($phids);
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

    $pager = new AphrontPagerView();
    $pager->setPageSize($page_size);
    $pager->setOffset($offset);
    if (count($history) == $page_size + 1) {
      array_pop($history);
      $pager->setHasMorePages(true);
    } else {
      $pager->setHasMorePages(false);
    }
    $pager->setURI($request->getRequestURI(), 'page');

    $content = array();

    $content[] = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'history',
      ));

    if ($request->getBool('copies')) {
      $button_title = 'Hide Copies/Branches';
      $copies_new = null;
    } else {
      $button_title = 'Show Copies/Branches';
      $copies_new = true;
    }

    $button = phutil_render_tag(
      'a',
      array(
        'class'   => 'button small grey',
        'href'    => $request->getRequestURI()->alter('copies', $copies_new),
      ),
      phutil_escape_html($button_title));

    $history_table = new DiffusionHistoryTableView();
    $history_table->setDiffusionRequest($drequest);
    $history_table->setHandles($handles);
    $history_table->setHistory($history);

    $history_panel = new AphrontPanelView();
    $history_panel->setHeader('History');
    $history_panel->addButton($button);
    $history_panel->appendChild($history_table);
    $history_panel->appendChild($pager);

    $content[] = $history_panel;

    // TODO: Sometimes we do have a change view, we need to look at the most
    // recent history entry to figure it out.

    $nav = $this->buildSideNav('history', false);
    $nav->appendChild($content);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'history',
      ));
  }

}
