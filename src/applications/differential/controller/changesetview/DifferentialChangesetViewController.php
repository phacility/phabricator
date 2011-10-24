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


  public function shouldRequireLogin() {
    return !$this->allowsAnonymousAccess();
  }

  public function processRequest() {
    $request = $this->getRequest();

    $author_phid = $request->getUser()->getPHID();

    $rendering_reference = $request->getStr('ref');
    $parts = explode('/', $rendering_reference);
    if (count($parts) == 2) {
      list($id, $vs) = $parts;
    } else {
      $id = $parts[0];
      $vs = 0;
    }

    $id = (int)$id;
    $vs = (int)$vs;

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

      $engine = new PhabricatorDifferenceEngine();
      $synthetic = $engine->generateChangesetFromFileContent(
        $left_data,
        $right_data);

      $choice = nonempty($left, $right);
      $choice->attachHunks($synthetic->getHunks());

      $changeset = $choice;
      $changeset->setID(null);
    }

    $spec = $request->getStr('range');
    list($range_s, $range_e, $mask) =
      DifferentialChangesetParser::parseRangeSpecification($spec);

    $parser = new DifferentialChangesetParser();
    $parser->setChangeset($changeset);
    $parser->setRenderingReference($rendering_reference);
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

    $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();
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

    Javelin::initBehavior('differential-comment-jump', array());

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
      id(new DifferentialPrimaryPaneView())
        ->setLineWidthFromChangesets(array($changeset))
        ->appendChild(
          '<div class="differential-review-stage" '.
            'id="differential-review-stage">'.
            $detail->render().
          '</div>');

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
