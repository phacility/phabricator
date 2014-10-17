<?php

final class AlmanacServiceEditController
  extends AlmanacServiceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $list_uri = $this->getApplicationURI('service/');

    $id = $request->getURIData('id');
    if ($id) {
      $service = id(new AlmanacServiceQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$service) {
        return new Aphront404Response();
      }

      $is_new = false;
      $service_uri = $service->getURI();
      $cancel_uri = $service_uri;
      $title = pht('Edit Service');
      $save_button = pht('Save Changes');
    } else {
      $this->requireApplicationCapability(
        AlmanacCreateServicesCapability::CAPABILITY);

      $service = AlmanacService::initializeNewService();
      $is_new = true;

      $cancel_uri = $list_uri;
      $title = pht('Create Service');
      $save_button = pht('Create Service');
    }

    $v_name = $service->getName();
    $e_name = true;
    $validation_exception = null;

    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_view = $request->getStr('viewPolicy');
      $v_edit = $request->getStr('editPolicy');

      $type_name = AlmanacServiceTransaction::TYPE_NAME;
      $type_view = PhabricatorTransactions::TYPE_VIEW_POLICY;
      $type_edit = PhabricatorTransactions::TYPE_EDIT_POLICY;

      $xactions = array();

      $xactions[] = id(new AlmanacServiceTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $xactions[] = id(new AlmanacServiceTransaction())
        ->setTransactionType($type_view)
        ->setNewValue($v_view);

      $xactions[] = id(new AlmanacServiceTransaction())
        ->setTransactionType($type_edit)
        ->setNewValue($v_edit);

      $editor = id(new AlmanacServiceEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($service, $xactions);

        $service_uri = $service->getURI();
        return id(new AphrontRedirectResponse())->setURI($service_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $e_name = $ex->getShortMessage($type_name);

        $service->setViewPolicy($v_view);
        $service->setEditPolicy($v_edit);
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($service)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($service)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($service)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($save_button));

    $box = id(new PHUIObjectBoxView())
      ->setValidationException($validation_exception)
      ->setHeaderText($title)
      ->appendChild($form);

    $crumbs = $this->buildApplicationCrumbs();
    if ($is_new) {
      $crumbs->addTextCrumb(pht('Create Service'));
    } else {
      $crumbs->addTextCrumb($service->getName(), $service_uri);
      $crumbs->addTextCrumb(pht('Edit'));
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
      ));
  }

}
