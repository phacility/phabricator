<?php

final class AlmanacNetworkEditController
  extends AlmanacNetworkController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $list_uri = $this->getApplicationURI('network/');

    $id = $request->getURIData('id');
    if ($id) {
      $network = id(new AlmanacNetworkQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$network) {
        return new Aphront404Response();
      }

      $is_new = false;
      $network_uri = $this->getApplicationURI('network/'.$network->getID().'/');
      $cancel_uri = $network_uri;
      $title = pht('Edit Network');
      $save_button = pht('Save Changes');
    } else {
      $this->requireApplicationCapability(
        AlmanacCreateNetworksCapability::CAPABILITY);

      $network = AlmanacNetwork::initializeNewNetwork();
      $is_new = true;

      $cancel_uri = $list_uri;
      $title = pht('Create Network');
      $save_button = pht('Create Network');
    }

    $v_name = $network->getName();
    $e_name = true;
    $validation_exception = null;

    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_view = $request->getStr('viewPolicy');
      $v_edit = $request->getStr('editPolicy');

      $type_name = AlmanacNetworkTransaction::TYPE_NAME;
      $type_view = PhabricatorTransactions::TYPE_VIEW_POLICY;
      $type_edit = PhabricatorTransactions::TYPE_EDIT_POLICY;

      $xactions = array();

      $xactions[] = id(new AlmanacNetworkTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $xactions[] = id(new AlmanacNetworkTransaction())
        ->setTransactionType($type_view)
        ->setNewValue($v_view);

      $xactions[] = id(new AlmanacNetworkTransaction())
        ->setTransactionType($type_edit)
        ->setNewValue($v_edit);

      $editor = id(new AlmanacNetworkEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($network, $xactions);

        $id = $network->getID();
        $network_uri = $this->getApplicationURI("network/{$id}/");
        return id(new AphrontRedirectResponse())->setURI($network_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $e_name = $ex->getShortMessage($type_name);

        $network->setViewPolicy($v_view);
        $network->setEditPolicy($v_edit);
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($network)
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
          ->setPolicyObject($network)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($network)
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
      $crumbs->addTextCrumb(pht('Create Network'));
    } else {
      $crumbs->addTextCrumb($network->getName(), $network_uri);
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
