<?php

final class PhabricatorOwnersEditController
  extends PhabricatorOwnersController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $id = $request->getURIData('id');
    if ($id) {
      $package = id(new PhabricatorOwnersPackageQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            // TODO: Support this capability.
            // PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->needOwners(true)
        ->executeOne();
      if (!$package) {
        return new Aphront404Response();
      }
      $is_new = false;
    } else {
      $package = PhabricatorOwnersPackage::initializeNewPackage($viewer);
      $is_new = true;
    }

    $e_name = true;

    $v_name = $package->getName();
    $v_owners = mpull($package->getOwners(), 'getUserPHID');
    $v_auditing = $package->getAuditingEnabled();
    $v_description = $package->getDescription();
    $v_status = $package->getStatus();

    $field_list = PhabricatorCustomField::getObjectFields(
      $package,
      PhabricatorCustomField::ROLE_EDIT);
    $field_list->setViewer($viewer);
    $field_list->readFieldsFromStorage($package);

    $errors = array();
    if ($request->isFormPost()) {
      $xactions = array();

      $v_name = $request->getStr('name');
      $v_owners = $request->getArr('owners');
      $v_auditing = ($request->getStr('auditing') == 'enabled');
      $v_description = $request->getStr('description');
      $v_status = $request->getStr('status');

      $type_name = PhabricatorOwnersPackageTransaction::TYPE_NAME;
      $type_owners = PhabricatorOwnersPackageTransaction::TYPE_OWNERS;
      $type_auditing = PhabricatorOwnersPackageTransaction::TYPE_AUDITING;
      $type_description = PhabricatorOwnersPackageTransaction::TYPE_DESCRIPTION;
      $type_status = PhabricatorOwnersPackageTransaction::TYPE_STATUS;

      $xactions[] = id(new PhabricatorOwnersPackageTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $xactions[] = id(new PhabricatorOwnersPackageTransaction())
        ->setTransactionType($type_owners)
        ->setNewValue($v_owners);

      $xactions[] = id(new PhabricatorOwnersPackageTransaction())
        ->setTransactionType($type_auditing)
        ->setNewValue($v_auditing);

      $xactions[] = id(new PhabricatorOwnersPackageTransaction())
        ->setTransactionType($type_description)
        ->setNewValue($v_description);

      if (!$is_new) {
        $xactions[] = id(new PhabricatorOwnersPackageTransaction())
          ->setTransactionType($type_status)
          ->setNewValue($v_status);
      }

      $field_xactions = $field_list->buildFieldTransactionsFromRequest(
        new PhabricatorOwnersPackageTransaction(),
        $request);

      $xactions = array_merge($xactions, $field_xactions);

      $editor = id(new PhabricatorOwnersPackageTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($package, $xactions);

        $id = $package->getID();
        if ($is_new) {
          $next_uri = '/owners/paths/'.$id.'/';
        } else {
          $next_uri = '/owners/package/'.$id.'/';
        }

        return id(new AphrontRedirectResponse())->setURI($next_uri);
      } catch (AphrontDuplicateKeyQueryException $ex) {
        $e_name = pht('Duplicate');
        $errors[] = pht('Package name must be unique.');
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_name = $ex->getShortMessage($type_name);
      }
    }

    if ($is_new) {
      $cancel_uri = '/owners/';
      $title = pht('New Package');
      $button_text = pht('Continue');
    } else {
      $cancel_uri = '/owners/package/'.$package->getID().'/';
      $title = pht('Edit Package');
      $button_text = pht('Save Package');
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($v_name)
          ->setError($e_name))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorProjectOrUserDatasource())
          ->setLabel(pht('Owners'))
          ->setName('owners')
          ->setValue($v_owners));

    if (!$is_new) {
      $form->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Status'))
          ->setName('status')
          ->setValue($v_status)
          ->setOptions($package->getStatusNameMap()));
    }

    $form->appendChild(
      id(new AphrontFormSelectControl())
        ->setName('auditing')
        ->setLabel(pht('Auditing'))
        ->setCaption(
          pht(
            'With auditing enabled, all future commits that touch '.
            'this package will be reviewed to make sure an owner '.
            'of the package is involved and the commit message has '.
            'a valid revision, reviewed by, and author.'))
        ->setOptions(
          array(
            'disabled'  => pht('Disabled'),
            'enabled'   => pht('Enabled'),
          ))
        ->setValue(($v_auditing ? 'enabled' : 'disabled')))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setLabel(pht('Description'))
          ->setName('description')
          ->setValue($v_description));

    $field_list->appendFieldsToForm($form);

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->addCancelButton($cancel_uri)
        ->setValue($button_text));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    if ($package->getID()) {
      $crumbs->addTextCrumb(
        $package->getName(),
        $this->getApplicationURI('package/'.$package->getID().'/'));
      $crumbs->addTextCrumb(pht('Edit'));
    } else {
      $crumbs->addTextCrumb(pht('New Package'));
    }

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
