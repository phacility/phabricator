<?php

final class DifferentialRevisionEditController
  extends DifferentialController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if (!$this->id) {
      $this->id = $request->getInt('revisionID');
    }

    if ($this->id) {
      $revision = id(new DifferentialRevisionQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->id))
        ->needRelationships(true)
        ->needReviewerStatus(true)
        ->needActiveDiffs(true)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$revision) {
        return new Aphront404Response();
      }
    } else {
      $revision = DifferentialRevision::initializeNewRevision($viewer);
      $revision->attachReviewerStatus(array());
    }

    $diff_id = $request->getInt('diffID');
    if ($diff_id) {
      $diff = id(new DifferentialDiffQuery())
        ->setViewer($viewer)
        ->withIDs(array($diff_id))
        ->executeOne();
      if (!$diff) {
        return new Aphront404Response();
      }
      if ($diff->getRevisionID()) {
        // TODO: Redirect?
        throw new Exception(
          pht('This diff is already attached to a revision!'));
      }
    } else {
      $diff = null;
    }

    if (!$diff) {
      if (!$revision->getID()) {
        throw new Exception(
          pht('You can not create a new revision without a diff!'));
      }
    } else {
      // TODO: It would be nice to show the diff being attached in the UI.
    }

    $field_list = PhabricatorCustomField::getObjectFields(
      $revision,
      PhabricatorCustomField::ROLE_EDIT);
    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($revision);

    if ($request->getStr('viaDiffView') && $diff) {
      $repo_key = id(new DifferentialRepositoryField())->getFieldKey();
      $repository_field = idx(
        $field_list->getFields(),
        $repo_key);
      if ($repository_field) {
        $repository_field->setValue($request->getStr($repo_key));
      }
      $view_policy_key = id(new DifferentialViewPolicyField())->getFieldKey();
      $view_policy_field = idx(
        $field_list->getFields(),
        $view_policy_key);
      if ($view_policy_field) {
        $view_policy_field->setValue($diff->getViewPolicy());
      }
    }

    $validation_exception = null;
    if ($request->isFormPost() && !$request->getStr('viaDiffView')) {

      $editor = id(new DifferentialTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      $xactions = $field_list->buildFieldTransactionsFromRequest(
        new DifferentialTransaction(),
        $request);

      if ($diff) {
        $repository_phid = null;
        $repository_tokenizer = $request->getArr(
          id(new DifferentialRepositoryField())->getFieldKey());
        if ($repository_tokenizer) {
          $repository_phid = reset($repository_tokenizer);
        }

        $xactions[] = id(new DifferentialTransaction())
          ->setTransactionType(DifferentialTransaction::TYPE_UPDATE)
          ->setNewValue($diff->getPHID());

        $editor->setRepositoryPHIDOverride($repository_phid);
      }

      $comments = $request->getStr('comments');
      if (strlen($comments)) {
        $xactions[] = id(new DifferentialTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
          ->attachComment(
            id(new DifferentialTransactionComment())
              ->setContent($comments));
      }

      try {
        $editor->applyTransactions($revision, $xactions);
        $revision_uri = '/D'.$revision->getID();
        return id(new AphrontRedirectResponse())->setURI($revision_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
      }
    }


    $form = new AphrontFormView();
    $form->setUser($request->getUser());
    if ($diff) {
      $form->addHiddenInput('diffID', $diff->getID());
    }

    if ($revision->getID()) {
      $form->setAction('/differential/revision/edit/'.$revision->getID().'/');
    } else {
      $form->setAction('/differential/revision/edit/');
    }

    if ($diff && $revision->getID()) {
      $form
        ->appendChild(
          id(new AphrontFormTextAreaControl())
            ->setLabel(pht('Comments'))
            ->setName('comments')
            ->setCaption(pht("Explain what's new in this diff."))
            ->setValue($request->getStr('comments')))
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue(pht('Save')))
        ->appendChild(
          id(new AphrontFormDividerControl()));
    }

    $field_list->appendFieldsToForm($form);

    $submit = id(new AphrontFormSubmitControl())
      ->setValue('Save');
    if ($diff) {
      $submit->addCancelButton('/differential/diff/'.$diff->getID().'/');
    } else {
      $submit->addCancelButton('/D'.$revision->getID());
    }

    $form->appendChild($submit);

    $crumbs = $this->buildApplicationCrumbs();
    if ($revision->getID()) {
      if ($diff) {
        $title = pht('Update Differential Revision');
        $crumbs->addTextCrumb(
          'D'.$revision->getID(),
          '/differential/diff/'.$diff->getID().'/');
      } else {
        $title = pht('Edit Differential Revision');
        $crumbs->addTextCrumb(
          'D'.$revision->getID(),
          '/D'.$revision->getID());
      }
    } else {
      $title = pht('Create New Differential Revision');
    }

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setValidationException($validation_exception)
      ->setForm($form);

    $crumbs->addTextCrumb($title);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => $title,
      ));
  }

}
