<?php

final class DiffusionCompareController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $content = array();

    $crumbs = $this->buildCrumbs(array('view' => 'compare'));

    $empty_title = null;
    $error_message = null;

    if ($repository->getVersionControlSystem() !=
          PhabricatorRepositoryType::REPOSITORY_TYPE_GIT) {
      $empty_title = pht('Not supported');
      $error_message = pht(
        'This feature is not yet supported for %s repositories.',
        $repository->getVersionControlSystem());
      $content[] = id(new PHUIInfoView())
        ->setTitle($empty_title)
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors(array($error_message));
    }

    $head_ref = $request->getStr('head');
    $against_ref = $request->getStr('against');

    $refs = id(new DiffusionCachedResolveRefsQuery())
      ->setRepository($repository)
      ->withRefs(array($head_ref, $against_ref))
      ->execute();


    if (count($refs) == 2) {
      $content[] = $this->buildCompareContent($drequest);
    } else {
      $content[] = $this->buildCompareForm($request, $refs);
    }


    return $this->newPage()
      ->setTitle(
        array(
          $repository->getName(),
          $repository->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild($content);
  }

  private function buildCompareForm(AphrontRequest $request, array $resolved) {
    $viewer = $this->getViewer();

    $head_ref = $request->getStr('head');
    $against_ref = $request->getStr('against');

    if (idx($resolved, $head_ref)) {
      $e_head = null;
    } else {
      $e_head = 'Not Found';
    }

    if (idx($resolved, $against_ref)) {
      $e_against = null;
    } else {
      $e_against = 'Not Found';
    }


    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->setMethod('GET')
      ->appendControl(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Head'))
          ->setName('head')
          ->setError($e_head)
          ->setValue($head_ref))
      ->appendControl(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Against'))
          ->setName('against')
          ->setError($e_against)
          ->setValue($against_ref))
      ->appendControl(
        id(new AphrontFormSubmitControl())
          ->setValue('Compare'));

    return $form;
  }

  private function buildCompareContent(DiffusionRequest $drequest) {
    $request = $this->getRequest();
    $repository = $drequest->getRepository();

    $head_ref = $request->getStr('head');
    $against_ref = $request->getStr('against');

    $content = array();

    $pager = id(new PHUIPagerView())
      ->readFromRequest($request);

    try {
      $history_results = $this->callConduitWithDiffusionRequest(
        'diffusion.historyquery',
        array(
          'commit' => $head_ref,
          'against' => $against_ref,
          'path' => $drequest->getPath(),
          'offset' => $pager->getOffset(),
          'limit' => $pager->getPageSize() + 1,
        ));
      $history = DiffusionPathChange::newFromConduit(
        $history_results['pathChanges']);

      $history = $pager->sliceResults($history);

      $history_exception = null;
    } catch (Exception $ex) {
      $history_results = null;
      $history = null;
      $history_exception = $ex;
    }

    if ($history_results) {
        $content[] = $this->buildCompareProperties($drequest, $history_results);
    }
    $content[] = $this->buildCompareForm(
      $request,
      array($head_ref => true, $against_ref => true));

    $content[] = $this->buildHistoryTable(
      $history_results,
      $history,
      $pager,
      $history_exception);

    $content[] = $this->renderTablePagerBox($pager);

    return $content;
  }

  private function buildCompareProperties($drequest, array $history_results) {
    $viewer = $this->getViewer();

    $request = $this->getRequest();
    $repository = $drequest->getRepository();

    $head_ref = $request->getStr('head');
    $against_ref = $request->getStr('against');

    $reverse_uri = $repository->getPathURI(
      "compare/?head=${against_ref}&against=${head_ref}");
    $actions = id(new PhabricatorActionListView());
    $actions->setUser($viewer);


    $actions->addAction(id(new PhabricatorActionView())
        ->setName(pht('Reverse Comparison'))
        ->setHref($reverse_uri)
        ->setIcon('fa-list'));

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $readme =
      'These are the commits that are reachable from **Head** commit and not '.
      'from the **Against** commit.';
    $readme = new PHUIRemarkupView($viewer, $readme);
    $view->addTextContent($readme);

    $view->addProperty(pht('Head'), $head_ref);
    $view->addProperty(pht('Against'), $against_ref);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setPolicyObject($drequest->getRepository());

    $box = id(new PHUIObjectBoxView())
      ->setUser($viewer)
      ->setHeader($header)
      ->addPropertyList($view);
    return $box;
  }

  private function buildHistoryTable(
    $history_results,
    $history,
    $pager,
    $history_exception) {

    $request = $this->getRequest();
    $viewer = $request->getUser();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    if ($history_exception) {
      if ($repository->isImporting()) {
        return $this->renderStatusMessage(
          pht('Still Importing...'),
          pht(
            'This repository is still importing. History is not yet '.
            'available.'));
      } else {
        return $this->renderStatusMessage(
          pht('Unable to Retrieve History'),
          $history_exception->getMessage());
      }
    }

    if (!$history) {
      return $this->renderStatusMessage(
        pht('Up to date'),
        new PHUIRemarkupView(
          $viewer,
          pht(
            '**Against** is strictly ahead of **Head** '.
            '- no commits are missing.')));
    }

    $history_table = id(new DiffusionHistoryTableView())
      ->setUser($viewer)
      ->setDiffusionRequest($drequest)
      ->setHistory($history);

    // TODO: Super sketchy?
    $history_table->loadRevisions();

    if ($history_results) {
      $history_table->setParents($history_results['parents']);
      $history_table->setIsHead(!$pager->getOffset());
      $history_table->setIsTail(!$pager->getHasMorePages());
    }

    // TODO also expose diff.

    $panel = new PHUIObjectBoxView();
    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Missing Commits'));
    $panel->setHeader($header);
    $panel->setTable($history_table);

    return $panel;
  }
}
