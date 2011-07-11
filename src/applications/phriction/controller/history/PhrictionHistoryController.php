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

class PhrictionHistoryController
  extends PhrictionController {

  private $slug;

  public function willProcessRequest(array $data) {
    $this->slug = $data['slug'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $document = id(new PhrictionDocument())->loadOneWhere(
      'slug = %s',
      PhrictionDocument::normalizeSlug($this->slug));

    if (!$document) {
      return new Aphront404Response();
    }

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));
    $pager->setURI($request->getRequestURI(), 'page');

    $history = id(new PhrictionContent())->loadAllWhere(
      'documentID = %d ORDER BY version DESC LIMIT %d, %d',
      $document->getID(),
      $pager->getOffset(),
      $pager->getPageSize() + 1);
    $history = $pager->sliceResults($history);

    $author_phids = mpull($history, 'getAuthorPHID');
    $handles = id(new PhabricatorObjectHandleData($author_phids))
      ->loadHandles();

    $rows = array();
    foreach ($history as $content) {

      $uri = PhrictionDocument::getSlugURI($document->getSlug());

      $rows[] = array(
        phabricator_date($content->getDateCreated(), $user),
        phabricator_time($content->getDateCreated(), $user),
        (int)$content->getVersion(),
        $handles[$content->getAuthorPHID()]->renderLink(),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Date',
        'Time',
        'Version',
        'Author',
      ));
    $table->setColumnClasses(
      array(
        '',
        'right',
        '',
        'wide',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Document History');
    $panel->appendChild($table);
    $panel->appendChild($pager);

    $slug = $document->getSlug();

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title'     => 'Document History',
        'history'   => PhrictionDocument::getSlugURI($slug, 'history'),
        'document'  => PhrictionDocument::getSlugURI($slug),
        'tab'       => 'history',
      ));

  }

}
