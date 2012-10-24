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

final class DifferentialChangesetViewController extends DifferentialController {

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
      $phid = idx($changeset->getMetadata(), "$view:binary-phid");
      if ($phid) {
        return id(new AphrontRedirectResponse())->setURI("/file/info/$phid/");
      }
      switch ($view) {
        case 'new':
          return $this->buildRawFileResponse($changeset, $is_new = true);
        case 'old':
          if ($vs && ($vs != -1)) {
            $vs_changeset = id(new DifferentialChangeset())->load($vs);
            if ($vs_changeset) {
              $vs_changeset->attachHunks($vs_changeset->loadHunks());
              return $this->buildRawFileResponse($vs_changeset, $is_new = true);
            }
          }
          return $this->buildRawFileResponse($changeset, $is_new = false);
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

      $choice = clone nonempty($left, $right);
      $choice->attachHunks($synthetic->getHunks());

      $changeset = $choice;
    }

    $coverage = null;
    if ($right && $right->getDiffID()) {
      $unit = id(new DifferentialDiffProperty())->loadOneWhere(
        'diffID = %d AND name = %s',
        $right->getDiffID(),
        'arc:unit');

      if ($unit) {
        $coverage = array();
        foreach ($unit->getData() as $result) {
          $result_coverage = idx($result, 'coverage');
          if (!$result_coverage) {
            continue;
          }
          $file_coverage = idx($result_coverage, $right->getFileName());
          if (!$file_coverage) {
            continue;
          }
          $coverage[] = $file_coverage;
        }

        $coverage = ArcanistUnitTestResult::mergeCoverage($coverage);
      }
    }

    $spec = $request->getStr('range');
    list($range_s, $range_e, $mask) =
      DifferentialChangesetParser::parseRangeSpecification($spec);

    $parser = new DifferentialChangesetParser();
    $parser->setCoverage($coverage);
    $parser->setChangeset($changeset);
    $parser->setRenderingReference($rendering_reference);
    $parser->setRenderCacheKey($render_cache_key);
    $parser->setRightSideCommentMapping($right_source, $right_new);
    $parser->setLeftSideCommentMapping($left_source, $left_new);
    $parser->setWhitespaceMode($request->getStr('whitespace'));

    if ($left && $right) {
      $parser->setOriginals($left, $right);
    }

    // Load both left-side and right-side inline comments.
    $inlines = $this->loadInlineComments(
      array($left_source, $right_source),
      $author_phid);

    if ($left_new) {
      $inlines = array_merge(
        $inlines,
        $this->buildLintInlineComments($left));
    }

    if ($right_new) {
      $inlines = array_merge(
        $inlines,
        $this->buildLintInlineComments($right));
    }

    $phids = array();
    foreach ($inlines as $inline) {
      $parser->parseInlineComment($inline);
      if ($inline->getAuthorPHID()) {
        $phids[$inline->getAuthorPHID()] = true;
      }
    }
    $phids = array_keys($phids);

    $handles = $this->loadViewerHandles($phids);
    $parser->setHandles($handles);

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($request->getUser());

    foreach ($inlines as $inline) {
      $engine->addObject(
        $inline,
        PhabricatorInlineCommentInterface::MARKUP_FIELD_BODY);
    }

    $engine->process();
    $parser->setMarkupEngine($engine);

    if ($request->isAjax()) {
      // TODO: This is sort of lazy, the effect is just to not render "Edit"
      // and "Reply" links on the "standalone view".
      $parser->setUser($request->getUser());
    }

    $output = $parser->render($range_s, $range_e, $mask);

    $mcov = $parser->renderModifiedCoverage();

    if ($request->isAjax()) {
      $coverage = array(
        'differential-mcoverage-'.md5($changeset->getFilename()) => $mcov,
      );

      return id(new PhabricatorChangesetResponse())
        ->setRenderedChangeset($output)
        ->setCoverage($coverage);
    }

    Javelin::initBehavior('differential-show-more', array(
      'uri' => '/differential/changeset/',
      'whitespace' => $request->getStr('whitespace'),
    ));

    Javelin::initBehavior('differential-comment-jump', array());

    $detail = new DifferentialChangesetDetailView();
    $detail->setChangeset($changeset);
    $detail->appendChild($output);
    $detail->setVsChangesetID($left_source);

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

  private function buildRawFileResponse(
    DifferentialChangeset $changeset,
    $is_new) {

    if ($is_new) {
      $key = 'raw:new:phid';
    } else {
      $key = 'raw:old:phid';
    }

    $metadata = $changeset->getMetadata();

    $file = null;
    $phid = idx($metadata, $key);
    if ($phid) {
      $file = id(new PhabricatorFile())->loadOneWhere(
        'phid = %s',
        $phid);
    }

    if (!$file) {
      // This is just building a cache of the changeset content in the file
      // tool, and is safe to run on a read pathway.
      $unguard = AphrontWriteGuard::beginScopedUnguardedWrites();

      if ($is_new) {
        $data = $changeset->makeNewFile();
      } else {
        $data = $changeset->makeOldFile();
      }

      $file = PhabricatorFile::newFromFileData(
        $data,
        array(
          'name'      => $changeset->getFilename(),
          'mime-type' => 'text/plain',
        ));

      $metadata[$key] = $file->getPHID();
      $changeset->setMetadata($metadata);
      $changeset->save();

      unset($unguard);
    }

    return id(new AphrontRedirectResponse())
      ->setURI($file->getBestURI());
  }

  private function buildLintInlineComments($changeset) {
    $lint = id(new DifferentialDiffProperty())->loadOneWhere(
      'diffID = %d AND name = %s',
      $changeset->getDiffID(),
      'arc:lint');
    if (!$lint) {
      return array();
    }
    $lint = $lint->getData();

    $inlines = array();
    foreach ($lint as $msg) {
      if ($msg['path'] != $changeset->getFilename()) {
        continue;
      }
      $inline = new DifferentialInlineComment();
      $inline->setChangesetID($changeset->getID());
      $inline->setIsNewFile(true);
      $inline->setSyntheticAuthor('Lint: '.$msg['name']);
      $inline->setLineNumber($msg['line']);
      $inline->setLineLength(0);

      $inline->setContent('%%%'.$msg['description'].'%%%');

      $inlines[] = $inline;
    }

    return $inlines;
  }

}
