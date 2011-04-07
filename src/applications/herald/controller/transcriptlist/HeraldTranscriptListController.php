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

class HeraldTranscriptListController extends HeraldController {

  public function processRequest() {

    $request = $this->getRequest();

    // Pull these objects manually since the serialized fields are gigantic.
    $transcript = new HeraldTranscript();

    $conn_r = $transcript->establishConnection('r');
    $phid = $request->getStr('phid');
    $where_clause = '';
    if ($phid) {
      $where_clause = qsprintf(
        $conn_r,
        'WHERE objectPHID = %s',
        $phid
      );
    }

    $data = queryfx_all(
      $conn_r,
      'SELECT id, objectPHID, time, duration, dryRun FROM %T
        %Q
        ORDER BY id DESC
        LIMIT 100',
      $transcript->getTableName(),
      $where_clause);

    /*

    $conn_r = smc_get_db('cdb.herald', 'r');

    $page_size = 100;

    $pager = new SimplePager();
    $pager->setPageSize($page_size);
    $pager->setOffset((((int)$request->getInt('page')) - 1) * $page_size);
    $pager->order('id', array('id'));


    $fbid = $request->getInt('fbid');
    if ($fbid) {
      $filter = qsprintf(
        $conn_r,
        'WHERE objectID = %d',
        $fbid);
    } else {
      $filter = '';
    }

    $data = $pager->select(
      $conn_r,
      'id, objectID, time, duration, dryRun FROM transcript %Q',
      $filter);
*/

    $handles = array();
    if ($data) {
      $phids = ipull($data, 'objectPHID', 'objectPHID');
      $handles = id(new PhabricatorObjectHandleData($phids))
        ->loadHandles();
    }

    $rows = array();
    foreach ($data as $xscript) {
      $rows[] = array(
        date('F jS', $xscript['time']),
        date('g:i:s A', $xscript['time']),
        $handles[$xscript['objectPHID']]->renderLink(),
        $xscript['dryRun'] ? 'Yes' : '',
        number_format((int)(1000 * $xscript['duration'])).' ms',
        phutil_render_tag(
          'a',
          array(
            'href' => '/herald/transcript/'.$xscript['id'].'/',
            'class' => 'button small grey',
          ),
          'View Transcript'),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Date',
        'Time',
        'Object',
        'Dry Run',
        'Duration',
        'View',
      ));
    $table->setColumnClasses(
      array(
        '',
        'right',
        'wide wrap',
        '',
        '',
        'action',
      ));


    $panel = new AphrontPanelView();
    $panel->setHeader('Herald Transcripts');
    $panel->appendChild($table);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Herald Transcripts',
        'tab' => 'transcripts',
      ));
  }

}
