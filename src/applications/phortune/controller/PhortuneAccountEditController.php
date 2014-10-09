<?php

final class PhortuneAccountEditController extends PhortuneController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if ($this->id) {
      $account = id(new PhortuneAccountQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->id))
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
      $is_new = true;
    }

    $v_name = $account->getName();
    $e_name = true;
    $validation_exception = null;

    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');

      $type_name = PhortuneAccountTransaction::TYPE_NAME;

      $xactions = array();
      $xactions[] = id(new PhortuneAccountTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      if ($is_new) {
        $xactions[] = id(new PhortuneAccountTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
          ->setMetadataValue(
            'edge:type',
            PhabricatorEdgeConfig::TYPE_ACCOUNT_HAS_MEMBER)
          ->setNewValue(
            array(
              '=' => array($viewer->getPHID() => $viewer->getPHID()),
            ));
      }

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
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($submit_button)
          ->addCancelButton($cancel_uri));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setValidationException($validation_exception)
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
