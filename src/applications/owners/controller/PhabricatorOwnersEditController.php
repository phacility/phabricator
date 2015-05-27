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
      $package = new PhabricatorOwnersPackage();
      $package->setPrimaryOwnerPHID($viewer->getPHID());

      $is_new = true;
    }

    $e_name = true;
    $e_primary = true;

    $errors = array();

    if ($request->isFormPost()) {
      $package->setName($request->getStr('name'));
      $package->setDescription($request->getStr('description'));
      $old_auditing_enabled = $package->getAuditingEnabled();
      $package->setAuditingEnabled(
        ($request->getStr('auditing') === 'enabled')
          ? 1
          : 0);

      $primary = $request->getArr('primary');
      $primary = reset($primary);
      $old_primary = $package->getPrimaryOwnerPHID();
      $package->setPrimaryOwnerPHID($primary);

      $owners = $request->getArr('owners');
      if ($primary) {
        array_unshift($owners, $primary);
      }
      $owners = array_unique($owners);

      if (!strlen($package->getName())) {
        $e_name = pht('Required');
        $errors[] = pht('Package name is required.');
      } else {
        $e_name = null;
      }

      if (!$package->getPrimaryOwnerPHID()) {
        $e_primary = pht('Required');
        $errors[] = pht('Package must have a primary owner.');
      } else {
        $e_primary = null;
      }

      if (!$errors) {
        $package->attachUnsavedOwners($owners);
        $package->attachUnsavedPaths(array());
        $package->attachOldAuditingEnabled($old_auditing_enabled);
        $package->attachOldPrimaryOwnerPHID($old_primary);
        try {
          id(new PhabricatorOwnersPackageEditor())
            ->setActor($viewer)
            ->setPackage($package)
            ->save();

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
        }
      }
    } else {
      $owners = $package->loadOwners();
      $owners = mpull($owners, 'getUserPHID');
    }

    $primary = $package->getPrimaryOwnerPHID();
    if ($primary) {
      $value_primary_owner = array($primary);
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
          ->setValue($package->getName())
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
          ->setValue($owners))
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
          ->setOptions(array(
            'disabled'  => pht('Disabled'),
            'enabled'   => pht('Enabled'),
          ))
          ->setValue(
            $package->getAuditingEnabled()
              ? 'enabled'
              : 'disabled'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Description'))
          ->setName('description')
          ->setValue($package->getDescription()))
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
