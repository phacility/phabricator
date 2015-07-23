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
    $e_primary = true;

    $v_name = $package->getName();
    $v_primary = $package->getPrimaryOwnerPHID();
    // TODO: Pull these off needOwners() on the Query.
    $v_owners = mpull($package->loadOwners(), 'getUserPHID');
    $v_auditing = $package->getAuditingEnabled();
    $v_description = $package->getDescription();


    $errors = array();
    if ($request->isFormPost()) {
      $xactions = array();

      $v_name = $request->getStr('name');
      $v_primary = head($request->getArr('primary'));
      $v_owners = $request->getArr('owners');
      $v_auditing = ($request->getStr('auditing') == 'enabled');
      $v_description = $request->getStr('description');

      if ($v_primary) {
        $v_owners[] = $v_primary;
        $v_owners = array_unique($v_owners);
      }

      $type_name = PhabricatorOwnersPackageTransaction::TYPE_NAME;
      $type_primary = PhabricatorOwnersPackageTransaction::TYPE_PRIMARY;
      $type_owners = PhabricatorOwnersPackageTransaction::TYPE_OWNERS;
      $type_auditing = PhabricatorOwnersPackageTransaction::TYPE_AUDITING;
      $type_description = PhabricatorOwnersPackageTransaction::TYPE_DESCRIPTION;

      $xactions[] = id(new PhabricatorOwnersPackageTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $xactions[] = id(new PhabricatorOwnersPackageTransaction())
        ->setTransactionType($type_primary)
        ->setNewValue($v_primary);

      $xactions[] = id(new PhabricatorOwnersPackageTransaction())
        ->setTransactionType($type_owners)
        ->setNewValue($v_owners);

      $xactions[] = id(new PhabricatorOwnersPackageTransaction())
        ->setTransactionType($type_auditing)
        ->setNewValue($v_auditing);

      $xactions[] = id(new PhabricatorOwnersPackageTransaction())
        ->setTransactionType($type_description)
        ->setNewValue($v_description);

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
        $e_primary = $ex->getShortMessage($type_primary);
      }
    }

    if ($v_primary) {
      $value_primary_owner = array($v_primary);
    } else {
      $value_primary_owner = array();
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
          ->setLabel(pht('Primary Owner'))
          ->setName('primary')
          ->setLimit(1)
          ->setValue($value_primary_owner)
          ->setError($e_primary))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorProjectOrUserDatasource())
          ->setLabel(pht('Owners'))
          ->setName('owners')
          ->setValue($v_owners))
      ->appendChild(
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
          ->setValue($v_description))
      ->appendChild(
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
