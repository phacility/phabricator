<?php

final class DiffusionDiffController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  protected function shouldLoadDiffusionRequest() {
    return false;
  }

  protected function processDiffusionRequest(AphrontRequest $request) {
    $data = $request->getURIMap();
    $data = $data + array(
      'dblob' => $this->getRequest()->getStr('ref'),
    );
    try {
      $drequest = DiffusionRequest::newFromAphrontRequestDictionary(
        $data,
        $request);
    } catch (Exception $ex) {
      return id(new Aphront404Response())
        ->setRequest($request);
    }
    $this->setDiffusionRequest($drequest);

    $drequest = $this->getDiffusionRequest();
    $user = $request->getUser();

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

    $parser = new DifferentialChangesetParser();
    $parser->setUser($user);
    $parser->setChangeset($changeset);
    $parser->setRenderingReference($drequest->generateURI(
      array(
        'action' => 'rendering-ref',
      )));

    $parser->setCharacterEncoding($request->getStr('encoding'));
    $parser->setHighlightAs($request->getStr('highlight'));

    $coverage = $drequest->loadCoverage();
    if ($coverage) {
      $parser->setCoverage($coverage);
    }

    $pquery = new DiffusionPathIDQuery(array($changeset->getFilename()));
    $ids = $pquery->loadPathIDs();
    $path_id = $ids[$changeset->getFilename()];

    $parser->setLeftSideCommentMapping($path_id, false);
    $parser->setRightSideCommentMapping($path_id, true);

    $parser->setWhitespaceMode(
      DifferentialChangesetParser::WHITESPACE_SHOW_ALL);

    $inlines = PhabricatorAuditInlineComment::loadDraftAndPublishedComments(
      $user,
      $drequest->loadCommit()->getPHID(),
      $path_id);

    if ($inlines) {
      foreach ($inlines as $inline) {
        $parser->parseInlineComment($inline);
      }

      $phids = mpull($inlines, 'getAuthorPHID');
      $handles = $this->loadViewerHandles($phids);
      $parser->setHandles($handles);
    }

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);

    foreach ($inlines as $inline) {
      $engine->addObject(
        $inline,
        PhabricatorInlineCommentInterface::MARKUP_FIELD_BODY);
    }

    $engine->process();

    $parser->setMarkupEngine($engine);

    $spec = $request->getStr('range');
    list($range_s, $range_e, $mask) =
      DifferentialChangesetParser::parseRangeSpecification($spec);
    $output = $parser->render($range_s, $range_e, $mask);

    return id(new PhabricatorChangesetResponse())
      ->setRenderedChangeset($output);
  }
}
