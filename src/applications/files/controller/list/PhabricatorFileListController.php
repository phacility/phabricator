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
    $user = $request->getUser();

    $upload_panel = $this->renderUploadPanel();

    $author = null;
    $author_username = $request->getStr('author');
    if ($author_username) {
      $author = id(new PhabricatorUser())->loadOneWhere(
        'userName = %s',
        $author_username);

      if (!$author) {
        return id(new Aphront404Response());
      }

      $title = 'Files Uploaded by '.phutil_escape_html($author->getUsername());
    } else {
      $title = 'Files';
    }

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));

    if ($author) {
      $files = id(new PhabricatorFile())->loadAllWhere(
        'authorPHID = %s ORDER BY id DESC LIMIT %d, %d',
        $author->getPHID(),
        $pager->getOffset(),
        $pager->getPageSize() + 1);
    } else {
      $files = id(new PhabricatorFile())->loadAllWhere(
        '1 = 1 ORDER BY id DESC LIMIT %d, %d',
        $pager->getOffset(),
        $pager->getPageSize() + 1);
    }

    $files = $pager->sliceResults($files);
    $pager->setURI($request->getRequestURI(), 'page');

    $phids = mpull($files, 'getAuthorPHID');
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

    $highlighted = $request->getStr('h');
    $highlighted = explode('-', $highlighted);
    $highlighted = array_fill_keys($highlighted, true);

    $rows = array();
    $rowc = array();
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

      if (isset($highlighted[$file->getID()])) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = '';
      }

      $rows[] = array(
        phutil_escape_html('F'.$file->getID()),
        $file->getAuthorPHID()
          ? $handles[$file->getAuthorPHID()]->renderLink()
          : null,
        phutil_render_tag(
          'a',
          array(
            'href' => $file->getViewURI(),
          ),
          phutil_escape_html($file->getName())),
        phutil_escape_html(number_format($file->getByteSize()).' bytes'),
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
        phabricator_date($file->getDateCreated(), $user),
        phabricator_time($file->getDateCreated(), $user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setRowClasses($rowc);
    $table->setHeaders(
      array(
        'File ID',
        'Author',
        'Name',
        'Size',
        '',
        '',
        '',
        'Created',
        '',
      ));
    $table->setColumnClasses(
      array(
        null,
        '',
        'wide pri',
        'right',
        'action',
        'action',
        'action',
        '',
        'right',
      ));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader($title);
    $panel->appendChild($pager);

    return $this->buildStandardPageResponse(
      array(
        $upload_panel,
        $panel,
      ),
      array(
        'title' => 'Files',
        'tab'   => 'files',
      ));
  }

  private function renderUploadPanel() {
    $request = $this->getRequest();
    $user = $request->getUser();

    require_celerity_resource('files-css');
    $upload_id = celerity_generate_unique_node_id();
    $panel_id  = celerity_generate_unique_node_id();

    $upload_panel = new AphrontPanelView();
    $upload_panel->setHeader('Upload Files');
    $upload_panel->setCreateButton(
      'Basic Uploader', '/file/upload/');

    $upload_panel->setWidth(AphrontPanelView::WIDTH_FULL);
    $upload_panel->setID($panel_id);

    $upload_panel->appendChild(
      phutil_render_tag(
        'div',
        array(
          'id'    => $upload_id,
          'style' => 'display: none;',
          'class' => 'files-drag-and-drop',
        ),
        ''));

    Javelin::initBehavior(
      'files-drag-and-drop',
      array(
        'uri'             => '/file/dropupload/',
        'browseURI'       => '/file/?author='.$user->getUsername(),
        'control'         => $upload_id,
        'target'          => $panel_id,
        'activatedClass'  => 'aphront-panel-view-drag-and-drop',
      ));

    return $upload_panel;
  }

}
