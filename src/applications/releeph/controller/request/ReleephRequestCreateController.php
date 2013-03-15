<?php

final class ReleephRequestCreateController extends ReleephController {

  const MAX_SUMMARY_LENGTH = 70;

  public function processRequest() {
    $request = $this->getRequest();

    // We arrived via /releeph/request/create/?branchID=$id
    $releeph_branch_id = $request->getInt('branchID');
    if ($releeph_branch_id) {
      $releeph_branch = id(new ReleephBranch())->load($releeph_branch_id);
    } else {
      // We arrived via /releeph/$project/$branch/request.
      //
      // If this throws an Exception, then somethind weird happened.
      $releeph_branch = $this->getReleephBranch();
    }

    $releeph_project = $releeph_branch->loadReleephProject();
    $repo = $releeph_project->loadPhabricatorRepository();

    $request_identifier = $request->getStr('requestIdentifierRaw');
    $e_request_identifier = true;

    $releeph_request = new ReleephRequest();

    $errors = array();

    $selector = $releeph_project->getReleephFieldSelector();
    $fields = $selector->getFieldSpecifications();
    foreach ($fields as $field) {
      $field
        ->setReleephProject($releeph_project)
        ->setReleephBranch($releeph_branch)
        ->setReleephRequest($releeph_request);
    }

    if ($request->isFormPost()) {
      foreach ($fields as $field) {
        if ($field->isEditable()) {
          try {
            $field->setValueFromAphrontRequest($request);
          } catch (ReleephFieldParseException $ex) {
            $errors[] = $ex->getMessage();
          }
        }
      }

      $pr_commit = null;
      $finder = id(new ReleephCommitFinder())
        ->setReleephProject($releeph_project);
      try {
        $pr_commit = $finder->fromPartial($request_identifier);
      } catch (Exception $e) {
        $e_request_identifier = 'Invalid';
        $errors[] =
          "Request {$request_identifier} is probably not a valid commit";
        $errors[] = $e->getMessage();
      }

      $pr_commit_data = null;
      if (!$errors) {
        $pr_commit_data = $pr_commit->loadCommitData();
        if (!$pr_commit_data) {
          $e_request_identifier = 'Not parsed yet';
          $errors[] = "The requested commit hasn't been parsed yet.";
        }
      }

      if (!$errors) {
        $existing = id(new ReleephRequest())
          ->loadOneWhere('requestCommitPHID = %s AND branchID = %d',
              $pr_commit->getPHID(), $releeph_branch->getID());

        if ($existing) {
          return id(new AphrontRedirectResponse())
            ->setURI('/releeph/request/edit/'.$existing->getID().
                     '?existing=1');
        }

        id(new ReleephRequestEditor($releeph_request))
          ->setActor($request->getUser())
          ->create($pr_commit, $releeph_branch);

        return id(new AphrontRedirectResponse())
          ->setURI($releeph_branch->getURI());
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
      $error_view->setTitle('Form Errors');
    }

    // For the typeahead
    $branch_cut_point = id(new PhabricatorRepositoryCommit())
      ->loadOneWhere(
          'phid = %s',
          $releeph_branch->getCutPointCommitPHID());

    // Build the form
    $form = id(new AphrontFormView())
      ->setUser($request->getUser());

    $origin = null;
    $diff_rev_id = $request->getStr('D');
    if ($diff_rev_id) {
      $diff_rev = id(new DifferentialRevision())->load($diff_rev_id);
      $origin = '/D'.$diff_rev->getID();
      $title = sprintf(
        'D%d: %s',
        $diff_rev_id,
        $diff_rev->getTitle());
      $form
        ->addHiddenInput('requestIdentifierRaw', 'D'.$diff_rev_id)
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel('Diff')
            ->setValue($title));
    } else {
      $origin = $releeph_branch->getURI();
      $form->appendChild(
        id(new ReleephRequestTypeaheadControl())
          ->setName('requestIdentifierRaw')
          ->setLabel('Commit ID')
          ->setRepo($repo)
          ->setValue($request_identifier)
          ->setError($e_request_identifier)
          ->setStartTime($branch_cut_point->getEpoch())
          ->setCaption(
            'Start typing to autocomplete on commit title, '.
            'or give a Phabricator commit identifier like rFOO1234'));
    }

    // Fields
    foreach ($fields as $field) {
      if ($field->isEditable()) {
        $control = $field->renderEditControl($request);
        $form->appendChild($control);
      }
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($origin)
          ->setValue('Request'));

    $panel = id(new AphrontPanelView())
      ->setHeader(
        'Request for '.
        $releeph_branch->getDisplayNameWithDetail())
      ->setWidth(AphrontPanelView::WIDTH_FORM)
      ->appendChild($form);

    return $this->buildStandardPageResponse(
      array($error_view, $panel),
      array('title' => 'Request pick'));
  }

}
