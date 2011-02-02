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

class DifferentialChangesetViewController extends DifferentialController {


  public function processRequest() {
    $request = $this->getRequest();

    $id = $request->getStr('id');
    $author_phid = $request->getUser()->getPHID();

    $changeset = id(new DifferentialChangeset())->load($id);
    if (!$changeset) {
      return new Aphront404Response();
    }

    $changeset->attachHunks($changeset->loadHunks());

    $range_s = null;
    $range_e = null;
    $mask = array();

    $range = $request->getStr('range');
    if ($range) {
      $match = null;
      if (preg_match('@^(\d+)-(\d+)(?:/(\d+)-(\d+))?$@', $range, $match)) {
        $range_s = (int)$match[1];
        $range_e = (int)$match[2];
        if (count($match) > 3) {
          $start = (int)$match[3];
          $len = (int)$match[4];
          for ($ii = $start; $ii < $start + $len; $ii++) {
            $mask[$ii] = true;
          }
        }
      }
    }

    $parser = new DifferentialChangesetParser();
    $parser->setChangeset($changeset);

    $phids = array();
    $inlines = $this->loadInlineComments($id, $author_phid);
    foreach ($inlines as $inline) {
      $parser->parseInlineComment($inline);
      $phids[$inline->getAuthorPHID()] = true;
    }
    $phids = array_keys($phids);

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();
    $parser->setHandles($handles);

    $factory = new DifferentialMarkupEngineFactory();
    $engine = $factory->newDifferentialCommentMarkupEngine();
    $parser->setMarkupEngine($engine);

    $output = $parser->render(null, $range_s, $range_e, $mask);

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())
        ->setContent($output);
    }

    Javelin::initBehavior('differential-show-more', array(
      'uri' => '/differential/changeset/',
    ));

    $detail = new DifferentialChangesetDetailView();
    $detail->setChangeset($changeset);
    $detail->appendChild($output);

    $output =
      '<div class="differential-primary-pane">'.
        '<div class="differential-review-stage">'.
          $detail->render().
        '</div>'.
      '</div>';

    return $this->buildStandardPageResponse(
      array(
        $output
      ),
      array(
        'title' => 'Changeset View',
      ));
  }

  private function loadInlineComments($changeset_id, $author_phid) {
    return id(new DifferentialInlineComment())->loadAllWhere(
      'changesetID = %d AND (commentID IS NOT NULL OR authorPHID = %s)',
      $changeset_id,
      $author_phid);
  }


}
