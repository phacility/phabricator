<?php

final class DifferentialChangesetViewController extends DifferentialController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

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

    $load_ids = array($id);
    if ($vs && ($vs != -1)) {
      $load_ids[] = $vs;
    }

    $changesets = id(new DifferentialChangesetQuery())
      ->setViewer($viewer)
      ->withIDs($load_ids)
      ->needHunks(true)
      ->execute();
    $changesets = mpull($changesets, null, 'getID');

    $changeset = idx($changesets, $id);
    if (!$changeset) {
      return new Aphront404Response();
    }

    $vs_changeset = null;
    if ($vs && ($vs != -1)) {
      $vs_changeset = idx($changesets, $vs);
      if (!$vs_changeset) {
        return new Aphront404Response();
      }
    }

    $view = $request->getStr('view');
    if ($view) {
      $phid = idx($changeset->getMetadata(), "$view:binary-phid");
      if ($phid) {
        return id(new AphrontRedirectResponse())->setURI("/file/info/$phid/");
      }
      switch ($view) {
        case 'new':
          return $this->buildRawFileResponse($changeset, $is_new = true);
        case 'old':
          if ($vs_changeset) {
            return $this->buildRawFileResponse($vs_changeset, $is_new = true);
          }
          return $this->buildRawFileResponse($changeset, $is_new = false);
        default:
          return new Aphront400Response();
      }
    }

    $old = array();
    $new = array();
    if (!$vs) {
      $right = $changeset;
      $left  = null;

      $right_source = $right->getID();
      $right_new = true;
      $left_source = $right->getID();
      $left_new = false;

      $render_cache_key = $right->getID();

      $old[] = $changeset;
      $new[] = $changeset;
    } else if ($vs == -1) {
      $right = null;
      $left = $changeset;

      $right_source = $left->getID();
      $right_new = false;
      $left_source = $left->getID();
      $left_new = true;

      $render_cache_key = null;

      $old[] = $changeset;
      $new[] = $changeset;
    } else {
      $right = $changeset;
      $left = $vs_changeset;

      $right_source = $right->getID();
      $right_new = true;
      $left_source = $left->getID();
      $left_new = true;

      $render_cache_key = null;

      $new[] = $left;
      $new[] = $right;
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

    if ($left_new || $right_new) {
      $diff_map = array();
      if ($left) {
        $diff_map[] = $left->getDiff();
      }
      if ($right) {
        $diff_map[] = $right->getDiff();
      }
      $diff_map = mpull($diff_map, null, 'getPHID');

      $buildables = id(new HarbormasterBuildableQuery())
        ->setViewer($viewer)
        ->withBuildablePHIDs(array_keys($diff_map))
        ->withManualBuildables(false)
        ->needBuilds(true)
        ->needTargets(true)
        ->execute();
      $buildables = mpull($buildables, null, 'getBuildablePHID');
      foreach ($diff_map as $diff_phid => $changeset_diff) {
        $changeset_diff->attachBuildable(idx($buildables, $diff_phid));
      }
    }

    $coverage = null;
    if ($right_new) {
      $coverage = $this->loadCoverage($right);
    }

    $spec = $request->getStr('range');
    list($range_s, $range_e, $mask) =
      DifferentialChangesetParser::parseRangeSpecification($spec);

    $parser = id(new DifferentialChangesetParser())
      ->setCoverage($coverage)
      ->setChangeset($changeset)
      ->setRenderingReference($rendering_reference)
      ->setRenderCacheKey($render_cache_key)
      ->setRightSideCommentMapping($right_source, $right_new)
      ->setLeftSideCommentMapping($left_source, $left_new);

    $parser->readParametersFromRequest($request);

    if ($left && $right) {
      $parser->setOriginals($left, $right);
    }

    $diff = $changeset->getDiff();
    $revision_id = $diff->getRevisionID();

    $can_mark = false;
    $object_owner_phid = null;
    $revision = null;
    if ($revision_id) {
      $revision = id(new DifferentialRevisionQuery())
        ->setViewer($viewer)
        ->withIDs(array($revision_id))
        ->executeOne();
      if ($revision) {
        $can_mark = ($revision->getAuthorPHID() == $viewer->getPHID());
        $object_owner_phid = $revision->getAuthorPHID();
      }
    }

    // Load both left-side and right-side inline comments.
    if ($revision) {
      $query = id(new DifferentialInlineCommentQuery())
        ->setViewer($viewer)
        ->needHidden(true)
        ->withRevisionPHIDs(array($revision->getPHID()));
      $inlines = $query->execute();
      $inlines = $query->adjustInlinesForChangesets(
        $inlines,
        $old,
        $new,
        $revision);
    } else {
      $inlines = array();
    }

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
    $engine->setViewer($viewer);

    foreach ($inlines as $inline) {
      $engine->addObject(
        $inline,
        PhabricatorInlineCommentInterface::MARKUP_FIELD_BODY);
    }

    $engine->process();

    $parser
      ->setUser($viewer)
      ->setMarkupEngine($engine)
      ->setShowEditAndReplyLinks(true)
      ->setCanMarkDone($can_mark)
      ->setObjectOwnerPHID($object_owner_phid)
      ->setRange($range_s, $range_e)
      ->setMask($mask);

    if ($request->isAjax()) {
      // NOTE: We must render the changeset before we render coverage
      // information, since it builds some caches.
      $rendered_changeset = $parser->renderChangeset();

      $mcov = $parser->renderModifiedCoverage();

      $coverage_data = array(
        'differential-mcoverage-'.md5($changeset->getFilename()) => $mcov,
      );

      return id(new PhabricatorChangesetResponse())
        ->setRenderedChangeset($rendered_changeset)
        ->setCoverage($coverage_data)
        ->setUndoTemplates($parser->getRenderer()->renderUndoTemplates());
    }

    $detail = id(new DifferentialChangesetListView())
      ->setUser($this->getViewer())
      ->setChangesets(array($changeset))
      ->setVisibleChangesets(array($changeset))
      ->setRenderingReferences(array($rendering_reference))
      ->setRenderURI('/differential/changeset/')
      ->setDiff($diff)
      ->setTitle(pht('Standalone View'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setParser($parser);

    if ($revision_id) {
      $detail->setInlineCommentControllerURI(
        '/differential/comment/inline/edit/'.$revision_id.'/');
    }

    $crumbs = $this->buildApplicationCrumbs();

    if ($revision_id) {
      $crumbs->addTextCrumb('D'.$revision_id, '/D'.$revision_id);
    }

    $diff_id = $diff->getID();
    if ($diff_id) {
      $crumbs->addTextCrumb(
        pht('Diff %d', $diff_id),
        $this->getApplicationURI('diff/'.$diff_id));
    }

    $crumbs->addTextCrumb($changeset->getDisplayFilename());
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Changeset View'))
      ->setHeaderIcon('fa-gear');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($detail);

    return $this->newPage()
      ->setTitle(pht('Changeset View'))
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildRawFileResponse(
    DifferentialChangeset $changeset,
    $is_new) {

    $viewer = $this->getViewer();

    if ($is_new) {
      $key = 'raw:new:phid';
    } else {
      $key = 'raw:old:phid';
    }

    $metadata = $changeset->getMetadata();

    $file = null;
    $phid = idx($metadata, $key);
    if ($phid) {
      $file = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($phid))
        ->execute();
      if ($file) {
        $file = head($file);
      }
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

      $diff = $changeset->getDiff();

      $file = PhabricatorFile::newFromFileData(
        $data,
        array(
          'name' => $changeset->getFilename(),
          'mime-type' => 'text/plain',
          'ttl.relative' => phutil_units('24 hours in seconds'),
          'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
        ));

      $file->attachToObject($diff->getPHID());

      $metadata[$key] = $file->getPHID();
      $changeset->setMetadata($metadata);
      $changeset->save();

      unset($unguard);
    }

    return $file->getRedirectResponse();
  }

  private function buildLintInlineComments($changeset) {
    $diff = $changeset->getDiff();

    $target_phids = $diff->getBuildTargetPHIDs();
    if (!$target_phids) {
      return array();
    }

    $messages = id(new HarbormasterBuildLintMessage())->loadAllWhere(
      'buildTargetPHID IN (%Ls) AND path = %s',
      $target_phids,
      $changeset->getFilename());

    if (!$messages) {
      return array();
    }

    $template = id(new DifferentialInlineComment())
        ->setChangesetID($changeset->getID())
        ->setIsNewFile(1)
        ->setLineLength(0);

    $inlines = array();
    foreach ($messages as $message) {
      $description = $message->getProperty('description');

      $inlines[] = id(clone $template)
        ->setSyntheticAuthor(pht('Lint: %s', $message->getName()))
        ->setLineNumber($message->getLine())
        ->setContent($description);
    }

    return $inlines;
  }

  private function loadCoverage(DifferentialChangeset $changeset) {
    $target_phids = $changeset->getDiff()->getBuildTargetPHIDs();
    if (!$target_phids) {
      return null;
    }

    $unit = id(new HarbormasterBuildUnitMessage())->loadAllWhere(
      'buildTargetPHID IN (%Ls)',
      $target_phids);

    if (!$unit) {
      return null;
    }

    $coverage = array();
    foreach ($unit as $message) {
      $test_coverage = $message->getProperty('coverage');
      if ($test_coverage === null) {
        continue;
      }
      $coverage_data = idx($test_coverage, $changeset->getFileName());
      if (!strlen($coverage_data)) {
        continue;
      }
      $coverage[] = $coverage_data;
    }

    if (!$coverage) {
      return null;
    }

    return ArcanistUnitTestResult::mergeCoverage($coverage);
  }

}
