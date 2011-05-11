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

    $author_phid = $request->getUser()->getPHID();

    $id = $request->getStr('id');
    $vs = $request->getInt('vs');


    $changeset = id(new DifferentialChangeset())->load($id);
    if (!$changeset) {
      return new Aphront404Response();
    }

    $view = $request->getStr('view');
    if ($view) {
      $changeset->attachHunks($changeset->loadHunks());
      switch ($view) {
        case 'new':
          return $this->buildRawFileResponse($changeset->makeNewFile());
        case 'old':
          return $this->buildRawFileResponse($changeset->makeOldFile());
        default:
          return new Aphront400Response();
      }
    }

    if ($vs && ($vs != -1)) {
      $vs_changeset = id(new DifferentialChangeset())->load($vs);
      if (!$vs_changeset) {
        return new Aphront404Response();
      }
    }

    if (!$vs) {
      $right = $changeset;
      $left  = null;

      $right_source = $right->getID();
      $right_new = true;
      $left_source = $right->getID();
      $left_new = false;

      $render_cache_key = $right->getID();
    } else if ($vs == -1) {
      $right = null;
      $left = $changeset;

      $right_source = $left->getID();
      $right_new = false;
      $left_source = $left->getID();
      $left_new = true;

      $render_cache_key = null;
    } else {
      $right = $changeset;
      $left = $vs_changeset;

      $right_source = $right->getID();
      $right_new = true;
      $left_source = $left->getID();
      $left_new = true;

      $render_cache_key = null;
    }

    if ($left) {
      $left->attachHunks($left->loadHunks());
    }

    if ($right) {
      $right->attachHunks($right->loadHunks());
    }

    if ($left) {

      $left_data = $left->makeNewFile();
      if ($right) {
        $right_data = $right->makeNewFile();
      } else {
        $right_data = $left->makeOldFile();
      }

      $left_tmp = new TempFile();
      $right_tmp = new TempFile();
      Filesystem::writeFile($left_tmp, $left_data);
      Filesystem::writeFile($right_tmp, $right_data);
      list($err, $stdout) = exec_manual(
        '/usr/bin/diff -U65535 %s %s',
        $left_tmp,
        $right_tmp);

      $choice = nonempty($left, $right);
      if ($stdout) {
        $parser = new ArcanistDiffParser();
        $changes = $parser->parseDiff($stdout);
        $diff = DifferentialDiff::newFromRawChanges($changes);
        $changesets = $diff->getChangesets();
        $first = reset($changesets);
        $choice->attachHunks($first->getHunks());
      } else {
        $choice->attachHunks(array());
      }

      $changeset = $choice;
      $changeset->setID(null);
    }

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
    $parser->setRenderCacheKey($render_cache_key);
    $parser->setRightSideCommentMapping($right_source, $right_new);
    $parser->setLeftSideCommentMapping($left_source, $left_new);
    $parser->setWhitespaceMode($request->getStr('whitespace'));

    // Load both left-side and right-side inline comments.
    $inlines = $this->loadInlineComments(
      array($left_source, $right_source),
      $author_phid);

    $phids = array();
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

    if ($request->isAjax()) {
      // TODO: This is sort of lazy, the effect is just to not render "Edit"
      // links on the "standalone view".
      $parser->setUser($request->getUser());
    }

    $output = $parser->render($range_s, $range_e, $mask);

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())
        ->setContent($output);
    }

    Javelin::initBehavior('differential-show-more', array(
      'uri' => '/differential/changeset/',
      'whitespace' => $request->getStr('whitespace'),
    ));

    $detail = new DifferentialChangesetDetailView();
    $detail->setChangeset($changeset);
    $detail->appendChild($output);

    if (!$vs) {
      $detail->addButton(
        phutil_render_tag(
          'a',
          array(
            'href' => $request->getRequestURI()->alter('view', 'old'),
            'class' => 'grey button small',
          ),
          'View Raw File (Old Version)'));
      $detail->addButton(
        phutil_render_tag(
          'a',
          array(
            'href' => $request->getRequestURI()->alter('view', 'new'),
            'class' => 'grey button small',
          ),
          'View Raw File (New Version)'));
    }

    $detail->setRevisionID($request->getInt('revision_id'));

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

  private function loadInlineComments(array $changeset_ids, $author_phid) {
    $changeset_ids = array_unique(array_filter($changeset_ids));
    if (!$changeset_ids) {
      return;
    }

    return id(new DifferentialInlineComment())->loadAllWhere(
      'changesetID IN (%Ld) AND (commentID IS NOT NULL OR authorPHID = %s)',
      $changeset_ids,
      $author_phid);
  }

  private function buildRawFileResponse($text) {
    return id(new AphrontFileResponse())
      ->setMimeType('text/plain')
      ->setContent($text);
  }

}
