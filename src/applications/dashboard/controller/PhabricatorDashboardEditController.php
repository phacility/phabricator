<?php

final class PhabricatorDashboardEditController
  extends PhabricatorDashboardController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if ($this->id) {
      $dashboard = id(new PhabricatorDashboardQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$dashboard) {
        return new Aphront404Response();
      }

      $is_new = false;
    } else {
      $dashboard = PhabricatorDashboard::initializeNewDashboard($viewer);

      $is_new = true;
    }

    $crumbs = $this->buildApplicationCrumbs();

    if ($is_new) {
      $title = pht('Create Dashboard');
      $header = pht('Create Dashboard');
      $button = pht('Create Dashboard');
      $cancel_uri = $this->getApplicationURI();

      $crumbs->addTextCrumb('Create Dashboard');
    } else {
      $id = $dashboard->getID();
      $cancel_uri = $this->getApplicationURI('view/'.$id.'/');

      $title = pht('Edit Dashboard %d', $dashboard->getID());
      $header = pht('Edit Dashboard "%s"', $dashboard->getName());
      $button = pht('Save Changes');

      $crumbs->addTextCrumb(pht('Dashboard %d', $id), $cancel_uri);
      $crumbs->addTextCrumb(pht('Edit'));
    }

    $v_name = $dashboard->getName();
    $e_name = true;

    $validation_exception = null;
    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');

      $xactions = array();

      $type_name = PhabricatorDashboardTransaction::TYPE_NAME;

      $xactions[] = id(new PhabricatorDashboardTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      try {
        $editor = id(new PhabricatorDashboardTransactionEditor())
          ->setActor($viewer)
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request)
          ->applyTransactions($dashboard, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI($this->getApplicationURI('view/'.$dashboard->getID().'/'));
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_name = $validation_exception->getShortMessage($type_name);
      }
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
        id(new AphrontFormSubmitControl())
          ->setValue($button)
          ->addCancelButton($cancel_uri));


    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($header)
      ->setForm($form)
      ->setValidationException($validation_exception);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
