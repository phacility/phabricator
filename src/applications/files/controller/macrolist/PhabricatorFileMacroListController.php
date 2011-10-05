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

class PhabricatorFileMacroListController extends PhabricatorFileController {

  public function processRequest() {

    $request = $this->getRequest();

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));

    $macro_table = new PhabricatorFileImageMacro();
    $macros = $macro_table->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d, %d',
      $pager->getOffset(),
      $pager->getPageSize());

    // Get an exact count since the size here is reasonably going to be a few
    // thousand at most in any reasonable case.
    $count = queryfx_one(
      $macro_table->establishConnection('r'),
      'SELECT COUNT(*) N FROM %T',
      $macro_table->getTableName());
    $count = $count['N'];

    $pager->setCount($count);
    $pager->setURI($request->getRequestURI(), 'page');

    $file_phids = mpull($macros, 'getFilePHID');
    $files = id(new PhabricatorFile())->loadAllWhere(
      "phid IN (%Ls)",
      $file_phids);
    $author_phids = mpull($files, 'getAuthorPHID', 'getPHID');
    $handles = id(new PhabricatorObjectHandleData($author_phids))
      ->loadHandles();

    $rows = array();
    foreach ($macros as $macro) {
      $src = PhabricatorFileURI::getViewURIForPHID($macro->getFilePHID());
      $file_phid = $macro->getFilePHID();
      $author_link = isset($author_phids[$file_phid])
        ? $handles[$author_phids[$file_phid]]->renderLink()
        : null;

      $rows[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => '/file/macro/edit/'.$macro->getID().'/',
          ),
          phutil_escape_html($macro->getName())),

        $author_link,
        phutil_render_tag(
          'a',
          array(
            'href'    => $src,
            'target'  => '_blank',
          ),
          phutil_render_tag(
            'img',
            array(
              'src' => $src,
            ))),
        javelin_render_tag(
          'a',
          array(
            'href' => '/file/macro/delete/'.$macro->getID().'/',
            'sigil' => 'workflow',
            'class' => 'grey small button',
          ),
          'Delete'),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Name',
        'Author',
        'Image',
        '',
      ));
    $table->setColumnClasses(
      array(
        'pri',
        '',
        'wide thumb',
        'action',
      ));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);

    $panel->setHeader('Image Macros');
    $panel->setCreateButton('New Image Macro', '/file/macro/edit/');
    $panel->appendChild($pager);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Image Macros',
        'tab'   => 'macros',
      ));
  }
}
