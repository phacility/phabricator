<?php

final class PhortuneAccountEditController extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if ($id) {
      $account = id(new PhortuneAccountQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$account) {
        return new Aphront404Response();
      }
      $is_new = false;
    } else {
      $account = PhortuneAccount::initializeNewAccount($viewer);
      $account->attachMemberPHIDs(array($viewer->getPHID()));
      $is_new = true;
    }

    $v_name = $account->getName();
    $e_name = true;

    $v_members = $account->getMemberPHIDs();
    $e_members = null;

    $validation_exception = null;

    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_members = $request->getArr('memberPHIDs');

      $type_name = PhortuneAccountTransaction::TYPE_NAME;
      $type_edge = PhabricatorTransactions::TYPE_EDGE;

      $xactions = array();
      $xactions[] = id(new PhortuneAccountTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $xactions[] = id(new PhortuneAccountTransaction())
        ->setTransactionType($type_edge)
        ->setMetadataValue(
          'edge:type',
          PhortuneAccountHasMemberEdgeType::EDGECONST)
        ->setNewValue(
          array(
            '=' => array_fuse($v_members),
          ));

      $editor = id(new PhortuneAccountEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($account, $xactions);

        $account_uri = $this->getApplicationURI($account->getID().'/');
        return id(new AphrontRedirectResponse())->setURI($account_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $e_name = $ex->getShortMessage($type_name);
        $e_members = $ex->getShortMessage($type_edge);
      }
    }

    $crumbs = $this->buildApplicationCrumbs();

    if ($is_new) {
      $cancel_uri = $this->getApplicationURI('account/');
      $crumbs->addTextCrumb(pht('Accounts'), $cancel_uri);
      $crumbs->addTextCrumb(pht('Create Account'));

      $title = pht('Create Payment Account');
      $submit_button = pht('Create Account');
    } else {
      $cancel_uri = $this->getApplicationURI($account->getID().'/');
      $crumbs->addTextCrumb($account->getName(), $cancel_uri);
      $crumbs->addTextCrumb(pht('Edit'));

      $title = pht('Edit %s', $account->getName());
      $submit_button = pht('Save Changes');
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name'))
          ->setValue($v_name)
          ->setError($e_name))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setLabel(pht('Members'))
          ->setName('memberPHIDs')
          ->setValue($v_members)
          ->setError($e_members))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($submit_button)
          ->addCancelButton($cancel_uri));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setValidationException($validation_exception)
      ->setForm($form);

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
