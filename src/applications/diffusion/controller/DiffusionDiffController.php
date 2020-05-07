<?php

final class DiffusionDiffController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  protected function getDiffusionBlobFromRequest(AphrontRequest $request) {
    return $request->getStr('ref');
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();

    if (!$request->isAjax()) {

      // This request came out of the dropdown menu, either "View Standalone"
      // or "View Raw File".

      $view = $request->getStr('view');
      if ($view == 'r') {
        $uri = $drequest->generateURI(
          array(
            'action' => 'browse',
            'params' => array(
              'view' => 'raw',
            ),
          ));
      } else {
        $uri = $drequest->generateURI(
          array(
            'action'  => 'change',
          ));
      }

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $data = $this->callConduitWithDiffusionRequest(
      'diffusion.diffquery',
      array(
        'commit' => $drequest->getCommit(),
        'path' => $drequest->getPath(),
      ));
    $drequest->updateSymbolicCommit($data['effectiveCommit']);
    $raw_changes = ArcanistDiffChange::newFromConduit($data['changes']);
    $diff = DifferentialDiff::newEphemeralFromRawChanges(
      $raw_changes);
    $changesets = $diff->getChangesets();
    $changeset = reset($changesets);

    if (!$changeset) {
      return new Aphront404Response();
    }

    $commit = $drequest->loadCommit();

    $viewstate_engine = id(new PhabricatorChangesetViewStateEngine())
      ->setViewer($viewer)
      ->setObjectPHID($commit->getPHID())
      ->setChangeset($changeset);

    $viewstate = $viewstate_engine->newViewStateFromRequest($request);

    if ($viewstate->getDiscardResponse()) {
      return new AphrontAjaxResponse();
    }

    $parser = id(new DifferentialChangesetParser())
      ->setViewer($viewer)
      ->setChangeset($changeset)
      ->setViewState($viewstate);

    $parser->setRenderingReference($drequest->generateURI(
      array(
        'action' => 'rendering-ref',
      )));

    $coverage = $drequest->loadCoverage();
    if ($coverage) {
      $parser->setCoverage($coverage);
    }

    $pquery = new DiffusionPathIDQuery(array($changeset->getFilename()));
    $ids = $pquery->loadPathIDs();
    $path_id = $ids[$changeset->getFilename()];

    $parser->setLeftSideCommentMapping($path_id, false);
    $parser->setRightSideCommentMapping($path_id, true);
    $parser->setCanMarkDone(
      ($commit->getAuthorPHID()) &&
      ($viewer->getPHID() == $commit->getAuthorPHID()));
    $parser->setObjectOwnerPHID($commit->getAuthorPHID());

    $inlines = id(new DiffusionDiffInlineCommentQuery())
      ->setViewer($viewer)
      ->withCommitPHIDs(array($commit->getPHID()))
      ->withPathIDs(array($path_id))
      ->withPublishedComments(true)
      ->withPublishableComments(true)
      ->execute();
    $inlines = mpull($inlines, 'newInlineCommentObject');

    if ($inlines) {
      foreach ($inlines as $inline) {
        $parser->parseInlineComment($inline);
      }

      $phids = mpull($inlines, 'getAuthorPHID');
      $handles = $this->loadViewerHandles($phids);
      $parser->setHandles($handles);
    }

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($viewer);

    foreach ($inlines as $inline) {
      $engine->addObject(
        $inline,
        PhabricatorInlineComment::MARKUP_FIELD_BODY);
    }

    $engine->process();

    $parser->setMarkupEngine($engine);

    $spec = $request->getStr('range');
    list($range_s, $range_e, $mask) =
      DifferentialChangesetParser::parseRangeSpecification($spec);

    $parser->setRange($range_s, $range_e);
    $parser->setMask($mask);

    return $parser->newChangesetResponse();
  }
}
