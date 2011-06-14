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

class PhabricatorPasteListController extends PhabricatorPasteController {

  public function processRequest() {

    $request = $this->getRequest();

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));

    $pastes = id(new PhabricatorPaste())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d, %d',
      $pager->getOffset(),
      $pager->getPageSize() + 1);

    $pastes = $pager->sliceResults($pastes);
    $pager->setURI($request->getRequestURI(), 'page');

    $phids = mpull($pastes, 'getAuthorPHID');
    $handles = array();
    if ($phids) {
      $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    }

    $rows = array();
    foreach ($pastes as $paste) {

      $handle = $handles[$paste->getAuthorPHID()];

      $rows[] = array(
        phutil_escape_html('P'.$paste->getID()),

        // TODO: Make this filter by user instead of going to their profile.
        phutil_render_tag(
          'a',
          array(
            'href' => '/p/'.$handle->getName().'/',
          ),
          phutil_escape_html($handle->getName())),

        phutil_render_tag(
          'a',
          array(
            'href' => '/P'.$paste->getID(),
          ),
          phutil_escape_html(
            nonempty(
              $paste->getTitle(),
              'Untitled Masterwork P'.$paste->getID()))),

        phutil_render_tag(
          'a',
          array(
            'href' => PhabricatorFileURI::getViewURIForPHID(
              $paste->getFilePHID()),
          ),
          phutil_escape_html($paste->getFilePHID())),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Paste ID',
        'Author',
        'Title',
        'File',
      ));

    $table->setColumnClasses(
      array(
        null,
        null,
        'wide pri',
        null,
      ));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);
    $panel->setHeader("Paste");
    $panel->setCreateButton('Paste Something', '/paste/');
    $panel->appendChild($table);
    $panel->appendChild($pager);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Paste List',
        'tab'   => 'list',
      )
    );
  }
}
