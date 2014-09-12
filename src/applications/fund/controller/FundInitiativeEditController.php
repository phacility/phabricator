<?php

final class FundInitiativeEditController
  extends FundController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if ($this->id) {
      $initiative = id(new FundInitiativeQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$initiative) {
        return new Aphront404Response();
      }
      $is_new = false;
    } else {
      $initiative = FundInitiative::initializeNewInitiative($viewer);
      $is_new = true;
    }

    if ($is_new) {
      $title = pht('Create Initiative');
      $button_text = pht('Create Initiative');
      $cancel_uri = $this->getApplicationURI();
    } else {
      $title = pht(
        'Edit %s %s',
        $initiative->getMonogram(),
        $initiative->getName());
      $button_text = pht('Save Changes');
      $cancel_uri = '/'.$initiative->getMonogram();
    }

    $e_name = true;
    $v_name = $initiative->getName();

    $v_desc = $initiative->getDescription();

    if ($is_new) {
      $v_projects = array();
    } else {
      $v_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $initiative->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $v_projects = array_reverse($v_projects);
    }

    $validation_exception = null;
    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_desc = $request->getStr('description');
      $v_view = $request->getStr('viewPolicy');
      $v_edit = $request->getStr('editPolicy');
      $v_projects = $request->getArr('projects');

      $type_name = FundInitiativeTransaction::TYPE_NAME;
      $type_desc = FundInitiativeTransaction::TYPE_DESCRIPTION;
      $type_view = PhabricatorTransactions::TYPE_VIEW_POLICY;
      $type_edit = PhabricatorTransactions::TYPE_EDIT_POLICY;

      $xactions = array();

      $xactions[] = id(new FundInitiativeTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $xactions[] = id(new FundInitiativeTransaction())
        ->setTransactionType($type_desc)
        ->setNewValue($v_desc);

      $xactions[] = id(new FundInitiativeTransaction())
        ->setTransactionType($type_view)
        ->setNewValue($v_view);

      $xactions[] = id(new FundInitiativeTransaction())
        ->setTransactionType($type_edit)
        ->setNewValue($v_edit);

      $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
      $xactions[] = id(new FundInitiativeTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $proj_edge_type)
        ->setNewValue(array('=' => array_fuse($v_projects)));

      $editor = id(new FundInitiativeEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($initiative, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI('/'.$initiative->getMonogram());
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_name = $ex->getShortMessage($type_name);

        $initiative->setViewPolicy($v_view);
        $initiative->setEditPolicy($v_edit);
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($initiative)
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
          ->setName('name')
          ->setLabel(pht('Name'))
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setName('description')
          ->setLabel(pht('Description'))
          ->setValue($v_desc))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Projects'))
          ->setName('projects')
          ->setValue($project_handles)
          ->setDatasource(new PhabricatorProjectDatasource()))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($initiative)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($initiative)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($button_text)
          ->addCancelButton($cancel_uri));

    $crumbs = $this->buildApplicationCrumbs();
    if ($is_new) {
      $crumbs->addTextCrumb(pht('Create Initiative'));
    } else {
      $crumbs->addTextCrumb(
        $initiative->getMonogram(),
        '/'.$initiative->getMonogram());
      $crumbs->addTextCrumb(pht('Edit'));
    }

    $box = id(new PHUIObjectBoxView())
      ->setValidationException($validation_exception)
      ->setHeaderText($title)
      ->appendChild($form);

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
