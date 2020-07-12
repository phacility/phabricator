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
    require_celerity_resource('diffusion-css');

    if (!$repository->supportsBranchComparison()) {
      return $this->newDialog()
        ->setTitle(pht('Not Supported'))
        ->appendParagraph(
          pht(
            'Branch comparison is not supported for this version control '.
            'system.'))
        ->addCancelButton($this->getApplicationURI(), pht('Okay'));
    }

    $head_ref = $request->getStr('head');
    $against_ref = $request->getStr('against');

    $must_prompt = false;
    if (!$request->isFormPost()) {
      if (!strlen($head_ref)) {
        $head_ref = $drequest->getSymbolicCommit();
        if (!strlen($head_ref)) {
          $head_ref = $drequest->getBranch();
        }
      }

      if (!strlen($against_ref)) {
        $default_branch = $repository->getDefaultBranch();
        if ($default_branch != $head_ref) {
          $against_ref = $default_branch;

          // If we filled this in by default, we want to prompt the user to
          // confirm that this is really what they want.
          $must_prompt = true;
        }
      }
    }

    $refs = $drequest->resolveRefs(
      array_filter(
        array(
          $head_ref,
          $against_ref,
        )));

    $identical = false;
    if ($head_ref === $against_ref) {
      $identical = true;
    } else {
      if (count($refs) == 2) {
        if ($refs[$head_ref] === $refs[$against_ref]) {
          $identical = true;
        }
      }
    }

    if ($must_prompt || count($refs) != 2 || $identical) {
      return $this->buildCompareDialog(
        $head_ref,
        $against_ref,
        $refs,
        $identical);
    }

    if ($request->isFormPost()) {
      // Redirect to a stable URI that can be copy/pasted.
      $compare_uri = $drequest->generateURI(
        array(
          'action' => 'compare',
          'head' => $head_ref,
          'against' => $against_ref,
        ));

      return id(new AphrontRedirectResponse())->setURI($compare_uri);
    }

    $crumbs = $this->buildCrumbs(
      array(
        'view' => 'compare',
      ));
    $crumbs->setBorder(true);

    $pager = id(new PHUIPagerView())
      ->readFromRequest($request);

    $history = null;
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

      $history_view = $this->newHistoryView(
        $history_results,
        $history,
        $pager,
        $head_ref,
        $against_ref);

    } catch (Exception $ex) {
      if ($repository->isImporting()) {
        $history_view = $this->renderStatusMessage(
          pht('Still Importing...'),
          pht(
            'This repository is still importing. History is not yet '.
            'available.'));
      } else {
        $history_view = $this->renderStatusMessage(
          pht('Unable to Retrieve History'),
          $ex->getMessage());
      }
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(
        pht(
          'Changes on %s but not %s',
          phutil_tag('em', array(), $head_ref),
          phutil_tag('em', array(), $against_ref)));

    $curtain = $this->buildCurtain($head_ref, $against_ref);

    $column_view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $history_view,
        ));

    return $this->newPage()
      ->setTitle(
        array(
          $repository->getName(),
          $repository->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild($column_view);
  }

  private function buildCompareDialog(
    $head_ref,
    $against_ref,
    array $resolved,
    $identical) {

    $viewer = $this->getViewer();
    $request = $this->getRequest();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $e_head = null;
    $e_against = null;
    $errors = array();
    if ($request->isFormPost()) {
      if (!strlen($head_ref)) {
        $e_head = pht('Required');
        $errors[] = pht(
          'You must provide two different commits to compare.');
      } else if (!isset($resolved[$head_ref])) {
        $e_head = pht('Not Found');
        $errors[] = pht(
          'Commit "%s" is not a valid commit in this repository.',
          $head_ref);
      }

      if (!strlen($against_ref)) {
        $e_against = pht('Required');
        $errors[] = pht(
          'You must provide two different commits to compare.');
      } else if (!isset($resolved[$against_ref])) {
        $e_against = pht('Not Found');
        $errors[] = pht(
          'Commit "%s" is not a valid commit in this repository.',
          $against_ref);
      }

      if ($identical) {
        $e_head = pht('Identical');
        $e_against = pht('Identical');
        $errors[] = pht(
          'Both references identify the same commit. You can not compare a '.
          'commit against itself.');
      }
    }

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
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
          ->setValue($against_ref));

    $cancel_uri = $repository->generateURI(
      array(
        'action' => 'browse',
      ));

    return $this->newDialog()
      ->setTitle(pht('Compare Against'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setErrors($errors)
      ->appendForm($form)
      ->addSubmitButton(pht('Compare'))
      ->addCancelButton($cancel_uri, pht('Cancel'));
  }

  private function buildCurtain($head_ref, $against_ref) {
    $viewer = $this->getViewer();
    $request = $this->getRequest();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $curtain = $this->newCurtainView(null);

    $reverse_uri = $drequest->generateURI(
      array(
        'action' => 'compare',
        'head' => $against_ref,
        'against' => $head_ref,
      ));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Reverse Comparison'))
        ->setHref($reverse_uri)
        ->setIcon('fa-refresh'));

    $compare_uri = $drequest->generateURI(
      array(
        'action' => 'compare',
        'head' => $head_ref,
      ));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Compare Against...'))
        ->setIcon('fa-code-fork')
        ->setWorkflow(true)
        ->setHref($compare_uri));

    // TODO: Provide a "Show Diff" action.

    return $curtain;
  }

  private function newHistoryView(
    array $results,
    array $history,
    PHUIPagerView $pager,
    $head_ref,
    $against_ref) {

    $request = $this->getRequest();
    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();

    if (!$history) {
      return $this->renderStatusMessage(
        pht('Up To Date'),
        pht(
          'There are no commits on %s that are not already on %s.',
          phutil_tag('strong', array(), $head_ref),
          phutil_tag('strong', array(), $against_ref)));
    }

    $history_view = id(new DiffusionCommitGraphView())
      ->setViewer($viewer)
      ->setDiffusionRequest($drequest)
      ->setHistory($history)
      ->setParents($results['parents'])
      ->setFilterParents(true)
      ->setIsHead(!$pager->getOffset())
      ->setIsTail(!$pager->getHasMorePages());

    return $history_view;
  }
}
