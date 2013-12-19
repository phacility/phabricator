<?php

final class ReleephRequestEditController extends ReleephProjectController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'requestID');
    parent::willProcessRequest($data);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $releeph_project = $this->getReleephProject();
    $releeph_branch = $this->getReleephBranch();

    $request_identifier = $request->getStr('requestIdentifierRaw');
    $e_request_identifier = true;

    // Load the RQ we're editing, or create a new one
    if ($this->id) {
      $rq = id(new ReleephRequestQuery())
        ->setViewer($user)
        ->withIDs(array($this->id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      $is_edit = true;
    } else {
      $is_edit = false;
      $rq = id(new ReleephRequest())
        ->setRequestUserPHID($user->getPHID())
        ->setBranchID($releeph_branch->getID())
        ->setInBranch(0);
    }

    // Load all the ReleephFieldSpecifications
    $selector = $this->getReleephProject()->getReleephFieldSelector();
    $fields = $selector->getFieldSpecifications();
    foreach ($fields as $field) {
      $field
        ->setReleephProject($releeph_project)
        ->setReleephBranch($releeph_branch)
        ->setReleephRequest($rq);
    }

    $field_list = PhabricatorCustomField::getObjectFields(
      $rq,
      PhabricatorCustomField::ROLE_EDIT);
    foreach ($field_list->getFields() as $field) {
      $field
        ->setReleephProject($releeph_project)
        ->setReleephBranch($releeph_branch)
        ->setReleephRequest($rq);
    }
    $field_list->readFieldsFromStorage($rq);


    // <aidehua> epriestley: Is it common to pass around a referer URL to
    // return from whence one came? [...]
    // <epriestley> If you only have two places, maybe consider some parameter
    // rather than the full URL.
    switch ($request->getStr('origin')) {
      case 'request':
        $origin_uri = '/RQ'.$rq->getID();
        break;

      case 'branch':
      default:
        $origin_uri = $releeph_branch->getURI();
        break;
    }

    // Make edits
    $errors = array();
    if ($request->isFormPost()) {
      $xactions = array();

      // The commit-identifier being requested...
      if (!$is_edit) {
        if ($request_identifier ===
          ReleephRequestTypeaheadControl::PLACEHOLDER) {

          $errors[] = "No commit ID was provided.";
          $e_request_identifier = 'Required';
        } else {
          $pr_commit = null;
          $finder = id(new ReleephCommitFinder())
            ->setUser($user)
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

          $xactions[] = id(new ReleephRequestTransaction())
            ->setTransactionType(ReleephRequestTransaction::TYPE_REQUEST)
            ->setNewValue($pr_commit->getPHID());

          $xactions[] = id(new ReleephRequestTransaction())
            ->setTransactionType(ReleephRequestTransaction::TYPE_USER_INTENT)
            // To help hide these implicit intents...
            ->setMetadataValue('isRQCreate', true)
            ->setMetadataValue('userPHID', $user->getPHID())
            ->setMetadataValue(
              'isAuthoritative',
              $releeph_project->isAuthoritative($user))
            ->setNewValue(ReleephRequest::INTENT_WANT);
        }
      }

      // TODO: This should happen implicitly while building transactions
      // instead.
      foreach ($field_list->getFields() as $field) {
        $field->readValueFromRequest($request);
      }

      if (!$errors) {
        foreach ($fields as $field) {
          if ($field->isEditable()) {
            try {
              $data = $request->getRequestData();
              $value = idx($data, $field->getRequiredStorageKey());
              $field->validate($value);
              $xactions[] = id(new ReleephRequestTransaction())
                ->setTransactionType(ReleephRequestTransaction::TYPE_EDIT_FIELD)
                ->setMetadataValue('fieldClass', get_class($field))
                ->setNewValue($value);
            } catch (ReleephFieldParseException $ex) {
              $errors[] = $ex->getMessage();
            }
          }
        }
      }

      if (!$errors) {
        $editor = id(new ReleephRequestTransactionalEditor())
          ->setActor($user)
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request);
        $editor->applyTransactions($rq, $xactions);
        return id(new AphrontRedirectResponse())->setURI($origin_uri);
      }
    }

    $releeph_branch->populateReleephRequestHandles($user, array($rq));
    $handles = $rq->getHandles();

    $age_string = '';
    if ($is_edit) {
      $age_string = phabricator_format_relative_time(
        time() - $rq->getDateCreated()) . ' ago';
    }

    // Warn the user if we've been redirected here because we tried to
    // re-request something.
    $notice_view = null;
    if ($request->getInt('existing')) {
      $notice_messages = array(
        'You are editing an existing pick request!',
        hsprintf(
          "Requested %s by %s",
          $age_string,
          $handles[$rq->getRequestUserPHID()]->renderLink())
      );
      $notice_view = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setErrors($notice_messages);
    }

    /**
     * Build the rest of the page
     */
    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
      $error_view->setTitle('Form Errors');
    }

    $form = id(new AphrontFormView())
      ->setUser($user);

    if ($is_edit) {
      $form
        ->appendChild(
          id(new AphrontFormMarkupControl())
            ->setLabel('Original Commit')
            ->setValue(
              $handles[$rq->getRequestCommitPHID()]->renderLink()))
        ->appendChild(
          id(new AphrontFormMarkupControl())
            ->setLabel('Requestor')
            ->setValue(hsprintf(
              '%s %s',
              $handles[$rq->getRequestUserPHID()]->renderLink(),
              $age_string)));
    } else {
      $origin = null;
      $diff_rev_id = $request->getStr('D');
      if ($diff_rev_id) {
        $diff_rev = id(new DifferentialRevisionQuery())
          ->setViewer($user)
          ->withIDs(array($diff_rev_id))
          ->executeOne();
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
        $repo = $releeph_project->loadPhabricatorRepository();
        $branch_cut_point = id(new PhabricatorRepositoryCommit())
          ->loadOneWhere(
              'phid = %s',
              $releeph_branch->getCutPointCommitPHID());
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
    }

    $field_list->appendFieldsToForm($form);

    $crumbs = $this->buildApplicationCrumbs();

    if ($is_edit) {
      $title = pht('Edit Releeph Request');
      $submit_name = pht('Save');

      $crumbs->addTextCrumb('RQ'.$rq->getID(), '/RQ'.$rq->getID());
      $crumbs->addTextCrumb(pht('Edit'));

    } else {
      $title = pht('Create Releeph Request');
      $submit_name = pht('Create');
      $crumbs->addTextCrumb(pht('New Request'));
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->addCancelButton($origin_uri, 'Cancel')
        ->setValue($submit_name));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $notice_view,
        $error_view,
        $form,
      ),
      array(
        'title' => $title,
      ));
  }
}
