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

class PhabricatorFileListController extends PhabricatorFileController {

  public function processRequest() {

    $request = $this->getRequest();

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));

    $files = id(new PhabricatorFile())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d, %d',
      $pager->getOffset(),
      $pager->getPageSize() + 1);

    $files = $pager->sliceResults($files);
    $pager->setURI($request->getRequestURI(), 'page');

    $rows = array();
    foreach ($files as $file) {
      if ($file->isViewableInBrowser()) {
        $view_button = phutil_render_tag(
          'a',
          array(
            'class' => 'small button grey',
            'href'  => '/file/view/'.$file->getPHID().'/',
          ),
          'View');
      } else {
        $view_button = null;
      }
      $rows[] = array(
        phutil_escape_html($file->getPHID()),
        phutil_escape_html($file->getName()),
        phutil_escape_html($file->getByteSize()),
        phutil_render_tag(
          'a',
          array(
            'class' => 'small button grey',
            'href'  => '/file/info/'.$file->getPHID().'/',
          ),
          'Info'),
        $view_button,
        phutil_render_tag(
          'a',
          array(
            'class' => 'small button grey',
            'href'  => '/file/download/'.$file->getPHID().'/',
          ),
          'Download'),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'PHID',
        'Name',
        'Size',
        '',
        '',
        '',
      ));
    $table->setColumnClasses(
      array(
        null,
        'wide',
        null,
        'action',
        'action',
        'action',
      ));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader('Files');
    $panel->setCreateButton('Upload File', '/file/upload/');
    $panel->appendChild($pager);

    return $this->buildStandardPageResponse($panel, array(
      'title' => 'Files',
      'tab'   => 'files',
      ));
  }
}
