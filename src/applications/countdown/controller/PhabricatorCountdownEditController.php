<?php

final class PhabricatorCountdownEditController
  extends PhabricatorCountdownController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if ($id) {
      $page_title = pht('Edit Countdown');
      $countdown = id(new PhabricatorCountdownQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$countdown) {
        return new Aphront404Response();
      }
      $date_value = AphrontFormDateControlValue::newFromEpoch(
        $viewer,
        $countdown->getEpoch());
      $v_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $countdown->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $v_projects = array_reverse($v_projects);
    } else {
      $page_title = pht('Create Countdown');
      $countdown = PhabricatorCountdown::initializeNewCountdown($viewer);
      $date_value = AphrontFormDateControlValue::newFromEpoch(
        $viewer, PhabricatorTime::getNow());
      $v_projects = array();
    }

    $errors = array();
    $e_text = true;
    $e_epoch = null;

    $v_text = $countdown->getTitle();
    $v_desc = $countdown->getDescription();
    $v_space = $countdown->getSpacePHID();
    $v_view = $countdown->getViewPolicy();
    $v_edit = $countdown->getEditPolicy();

    if ($request->isFormPost()) {
      $v_text = $request->getStr('title');
      $v_desc = $request->getStr('description');
      $v_space = $request->getStr('spacePHID');
      $date_value = AphrontFormDateControlValue::newFromRequest(
        $request,
        'epoch');
      $v_view = $request->getStr('viewPolicy');
      $v_edit = $request->getStr('editPolicy');
      $v_projects = $request->getArr('projects');

      $type_title = PhabricatorCountdownTransaction::TYPE_TITLE;
      $type_epoch = PhabricatorCountdownTransaction::TYPE_EPOCH;
      $type_description = PhabricatorCountdownTransaction::TYPE_DESCRIPTION;
      $type_space = PhabricatorTransactions::TYPE_SPACE;
      $type_view = PhabricatorTransactions::TYPE_VIEW_POLICY;
      $type_edit = PhabricatorTransactions::TYPE_EDIT_POLICY;

      $xactions = array();

      $xactions[] = id(new PhabricatorCountdownTransaction())
        ->setTransactionType($type_title)
        ->setNewValue($v_text);

      $xactions[] = id(new PhabricatorCountdownTransaction())
        ->setTransactionType($type_epoch)
        ->setNewValue($date_value);

      $xactions[] = id(new PhabricatorCountdownTransaction())
        ->setTransactionType($type_description)
        ->setNewValue($v_desc);

      $xactions[] = id(new PhabricatorCountdownTransaction())
        ->setTransactionType($type_space)
        ->setNewValue($v_space);

      $xactions[] = id(new PhabricatorCountdownTransaction())
        ->setTransactionType($type_view)
        ->setNewValue($v_view);

      $xactions[] = id(new PhabricatorCountdownTransaction())
        ->setTransactionType($type_edit)
        ->setNewValue($v_edit);

      $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
      $xactions[] = id(new PhabricatorCountdownTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $proj_edge_type)
        ->setNewValue(array('=' => array_fuse($v_projects)));

      $editor = id(new PhabricatorCountdownEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($countdown, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI('/'.$countdown->getMonogram());
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_title = $ex->getShortMessage($type_title);
        $e_epoch = $ex->getShortMessage($type_epoch);
      }

    }

    $crumbs = $this->buildApplicationCrumbs();

    $cancel_uri = '/countdown/';
    if ($countdown->getID()) {
      $cancel_uri = '/countdown/'.$countdown->getID().'/';
      $crumbs->addTextCrumb('C'.$countdown->getID(), $cancel_uri);
      $crumbs->addTextCrumb(pht('Edit'));
      $submit_label = pht('Save Changes');
    } else {
      $crumbs->addTextCrumb(pht('Create Countdown'));
      $submit_label = pht('Create Countdown');
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($countdown)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->setAction($request->getRequestURI()->getPath())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Title'))
          ->setValue($v_text)
          ->setName('title')
          ->setError($e_text))
      ->appendControl(
        id(new AphrontFormDateControl())
          ->setName('epoch')
          ->setLabel(pht('End Date'))
          ->setError($e_epoch)
          ->setValue($date_value))
      ->appendControl(
        id(new PhabricatorRemarkupControl())
          ->setName('description')
          ->setLabel(pht('Description'))
          ->setValue($v_desc))
      ->appendControl(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($countdown)
          ->setPolicies($policies)
          ->setSpacePHID($v_space)
          ->setValue($v_view)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW))
      ->appendControl(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($countdown)
          ->setPolicies($policies)
          ->setValue($v_edit)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Projects'))
          ->setName('projects')
          ->setValue($v_projects)
          ->setDatasource(new PhabricatorProjectDatasource()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($submit_label));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($page_title)
      ->setFormErrors($errors)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => $page_title,
      ));
  }

}
