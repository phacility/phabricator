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

    if ($is_new) {
      $v_projects = array();
    } else {
      $v_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $service->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $v_projects = array_reverse($v_projects);
    }

    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_view = $request->getStr('viewPolicy');
      $v_edit = $request->getStr('editPolicy');
      $v_projects = $request->getArr('projects');

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

      $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
      $xactions[] = id(new AlmanacServiceTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $proj_edge_type)
        ->setNewValue(array('=' => array_fuse($v_projects)));

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

    if ($v_projects) {
      $project_handles = $this->loadViewerHandles($v_projects);
    } else {
      $project_handles = array();
    }

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
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Projects'))
          ->setName('projects')
          ->setValue($project_handles)
          ->setDatasource(new PhabricatorProjectDatasource()))
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
