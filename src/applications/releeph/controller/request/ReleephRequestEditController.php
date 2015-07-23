<?php

final class ReleephRequestEditController extends ReleephBranchController {

  private $requestID;
  private $branchID;

  public function willProcessRequest(array $data) {
    $this->requestID = idx($data, 'requestID');
    $this->branchID = idx($data, 'branchID');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if ($this->requestID) {
      $pull = id(new ReleephRequestQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->requestID))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$pull) {
        return new Aphront404Response();
      }

      $branch = $pull->getBranch();

      $is_edit = true;
    } else {
      $branch = id(new ReleephBranchQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->branchID))
        ->executeOne();
      if (!$branch) {
        return new Aphront404Response();
      }

      $pull = id(new ReleephRequest())
        ->setRequestUserPHID($viewer->getPHID())
        ->setBranchID($branch->getID())
        ->setInBranch(0)
        ->attachBranch($branch);

      $is_edit = false;
    }
    $this->setBranch($branch);

    $product = $branch->getProduct();

    $request_identifier = $request->getStr('requestIdentifierRaw');
    $e_request_identifier = true;

    // Load all the ReleephFieldSpecifications
    $selector = $branch->getProduct()->getReleephFieldSelector();
    $fields = $selector->getFieldSpecifications();
    foreach ($fields as $field) {
      $field
        ->setReleephProject($product)
        ->setReleephBranch($branch)
        ->setReleephRequest($pull);
    }

    $field_list = PhabricatorCustomField::getObjectFields(
      $pull,
      PhabricatorCustomField::ROLE_EDIT);
    foreach ($field_list->getFields() as $field) {
      $field
        ->setReleephProject($product)
        ->setReleephBranch($branch)
        ->setReleephRequest($pull);
    }
    $field_list->readFieldsFromStorage($pull);


    if ($this->branchID) {
      $cancel_uri = $this->getApplicationURI('branch/'.$this->branchID.'/');
    } else {
      $cancel_uri = '/'.$pull->getMonogram();
    }

    // Make edits
    $errors = array();
    if ($request->isFormPost()) {
      $xactions = array();

      // The commit-identifier being requested...
      if (!$is_edit) {
        if ($request_identifier ===
          ReleephRequestTypeaheadControl::PLACEHOLDER) {

          $errors[] = pht('No commit ID was provided.');
          $e_request_identifier = pht('Required');
        } else {
          $pr_commit = null;
          $finder = id(new ReleephCommitFinder())
            ->setUser($viewer)
            ->setReleephProject($product);
          try {
            $pr_commit = $finder->fromPartial($request_identifier);
          } catch (Exception $e) {
            $e_request_identifier = pht('Invalid');
            $errors[] = pht(
              'Request %s is probably not a valid commit.',
              $request_identifier);
            $errors[] = $e->getMessage();
          }

          if (!$errors) {
            $object_phid = $finder->getRequestedObjectPHID();
            if (!$object_phid) {
              $object_phid = $pr_commit->getPHID();
            }

            $pull->setRequestedObjectPHID($object_phid);
          }
        }

        if (!$errors) {
          $existing = id(new ReleephRequest())
            ->loadOneWhere('requestCommitPHID = %s AND branchID = %d',
                $pr_commit->getPHID(), $branch->getID());
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
            ->setMetadataValue('userPHID', $viewer->getPHID())
            ->setMetadataValue(
              'isAuthoritative',
              $product->isAuthoritative($viewer))
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
          ->setActor($viewer)
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request);
        $editor->applyTransactions($pull, $xactions);
        return id(new AphrontRedirectResponse())->setURI($cancel_uri);
      }
    }

    $handle_phids = array(
      $pull->getRequestUserPHID(),
      $pull->getRequestCommitPHID(),
    );
    $handle_phids = array_filter($handle_phids);
    if ($handle_phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs($handle_phids)
        ->execute();
    } else {
      $handles = array();
    }

    $age_string = '';
    if ($is_edit) {
      $age_string = phutil_format_relative_time(
        time() - $pull->getDateCreated()).' ago';
    }

    // Warn the user if we've been redirected here because we tried to
    // re-request something.
    $notice_view = null;
    if ($request->getInt('existing')) {
      $notice_messages = array(
        pht('You are editing an existing pick request!'),
        pht(
          'Requested %s by %s',
          $age_string,
          $handles[$pull->getRequestUserPHID()]->renderLink()),
      );
      $notice_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->setErrors($notice_messages);
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    if ($is_edit) {
      $form
        ->appendChild(
          id(new AphrontFormMarkupControl())
            ->setLabel(pht('Original Commit'))
            ->setValue(
              $handles[$pull->getRequestCommitPHID()]->renderLink()))
        ->appendChild(
          id(new AphrontFormMarkupControl())
            ->setLabel(pht('Requestor'))
            ->setValue(hsprintf(
              '%s %s',
              $handles[$pull->getRequestUserPHID()]->renderLink(),
              $age_string)));
    } else {
      $origin = null;
      $diff_rev_id = $request->getStr('D');
      if ($diff_rev_id) {
        $diff_rev = id(new DifferentialRevisionQuery())
          ->setViewer($viewer)
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
              ->setLabel(pht('Diff'))
              ->setValue($title));
      } else {
        $origin = $branch->getURI();
        $repo = $product->getRepository();
        $branch_cut_point = id(new PhabricatorRepositoryCommit())
          ->loadOneWhere(
              'phid = %s',
              $branch->getCutPointCommitPHID());
        $form->appendChild(
          id(new ReleephRequestTypeaheadControl())
            ->setName('requestIdentifierRaw')
            ->setLabel(pht('Commit ID'))
            ->setRepo($repo)
            ->setValue($request_identifier)
            ->setError($e_request_identifier)
            ->setStartTime($branch_cut_point->getEpoch())
            ->setCaption(
              pht(
                'Start typing to autocomplete on commit title, '.
                'or give a Phabricator commit identifier like rFOO1234.')));
      }
    }

    $field_list->appendFieldsToForm($form);

    $crumbs = $this->buildApplicationCrumbs();

    if ($is_edit) {
      $title = pht('Edit Pull Request');
      $submit_name = pht('Save');

      $crumbs->addTextCrumb($pull->getMonogram(), '/'.$pull->getMonogram());
      $crumbs->addTextCrumb(pht('Edit'));
    } else {
      $title = pht('Create Pull Request');
      $submit_name = pht('Create Pull Request');

      $crumbs->addTextCrumb(pht('New Pull Request'));
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->addCancelButton($cancel_uri, pht('Cancel'))
        ->setValue($submit_name));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->appendChild($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $notice_view,
        $box,
      ),
      array(
        'title' => $title,
      ));
  }
}
