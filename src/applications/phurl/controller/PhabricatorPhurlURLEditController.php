<?php

final class PhabricatorPhurlURLEditController
  extends PhabricatorPhurlController {

  public function handleRequest(AphrontRequest $request) {
    $id = $request->getURIData('id');
    $is_create = !$id;

    $viewer = $request->getViewer();
    $user_phid = $viewer->getPHID();
    $error_name = true;
    $error_long_url = true;
    $validation_exception = null;

    $next_workflow = $request->getStr('next');
    $uri_query = $request->getStr('query');

    if ($is_create) {
      $url = PhabricatorPhurlURL::initializeNewPhurlURL(
        $viewer);
      $submit_label = pht('Create');
      $page_title = pht('Shorten URL');
      $subscribers = array();
      $cancel_uri = $this->getApplicationURI();
    } else {
      $url = id(new PhabricatorPhurlURLQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

      if (!$url) {
        return new Aphront404Response();
      }

      $submit_label = pht('Update');
      $page_title   = pht('Update URL');

      $subscribers = PhabricatorSubscribersQuery::loadSubscribersForPHID(
        $url->getPHID());

      $cancel_uri = '/U'.$url->getID();
    }

    if ($is_create) {
      $projects = array();
    } else {
      $projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $url->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $projects = array_reverse($projects);
    }

    $name = $url->getName();
    $long_url = $url->getLongURL();
    $description = $url->getDescription();
    $edit_policy = $url->getEditPolicy();
    $view_policy = $url->getViewPolicy();
    $space = $url->getSpacePHID();

    if ($request->isFormPost()) {
      $xactions = array();
      $name = $request->getStr('name');
      $long_url = $request->getStr('longURL');
      $projects = $request->getArr('projects');
      $description = $request->getStr('description');
      $subscribers = $request->getArr('subscribers');
      $edit_policy = $request->getStr('editPolicy');
      $view_policy = $request->getStr('viewPolicy');
      $space = $request->getStr('spacePHID');

      $xactions[] = id(new PhabricatorPhurlURLTransaction())
        ->setTransactionType(
          PhabricatorPhurlURLTransaction::TYPE_NAME)
        ->setNewValue($name);

      $xactions[] = id(new PhabricatorPhurlURLTransaction())
        ->setTransactionType(
          PhabricatorPhurlURLTransaction::TYPE_URL)
        ->setNewValue($long_url);

      $xactions[] = id(new PhabricatorPhurlURLTransaction())
        ->setTransactionType(
          PhabricatorTransactions::TYPE_SUBSCRIBERS)
        ->setNewValue(array('=' => array_fuse($subscribers)));

      $xactions[] = id(new PhabricatorPhurlURLTransaction())
        ->setTransactionType(
          PhabricatorPhurlURLTransaction::TYPE_DESCRIPTION)
        ->setNewValue($description);

      $xactions[] = id(new PhabricatorPhurlURLTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
        ->setNewValue($view_policy);

      $xactions[] = id(new PhabricatorPhurlURLTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
        ->setNewValue($edit_policy);

      $xactions[] = id(new PhabricatorPhurlURLTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_SPACE)
        ->setNewValue($space);

      $editor = id(new PhabricatorPhurlURLEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
        $xactions[] = id(new PhabricatorPhurlURLTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
          ->setMetadataValue('edge:type', $proj_edge_type)
          ->setNewValue(array('=' => array_fuse($projects)));

        $xactions = $editor->applyTransactions($url, $xactions);
        return id(new AphrontRedirectResponse())
          ->setURI($url->getURI());
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $error_name = $ex->getShortMessage(
          PhabricatorPhurlURLTransaction::TYPE_NAME);
        $error_long_url = $ex->getShortMessage(
          PhabricatorPhurlURLTransaction::TYPE_URL);
      }
    }

    $current_policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($url)
      ->execute();

    $name = id(new AphrontFormTextControl())
      ->setLabel(pht('Name'))
      ->setName('name')
      ->setValue($name)
      ->setError($error_name);

    $long_url = id(new AphrontFormTextControl())
      ->setLabel(pht('URL'))
      ->setName('longURL')
      ->setValue($long_url)
      ->setError($error_long_url);

    $projects = id(new AphrontFormTokenizerControl())
      ->setLabel(pht('Projects'))
      ->setName('projects')
      ->setValue($projects)
      ->setUser($viewer)
      ->setDatasource(new PhabricatorProjectDatasource());

    $description = id(new PhabricatorRemarkupControl())
      ->setLabel(pht('Description'))
      ->setName('description')
      ->setValue($description)
      ->setUser($viewer);

    $view_policies = id(new AphrontFormPolicyControl())
      ->setUser($viewer)
      ->setValue($view_policy)
      ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
      ->setPolicyObject($url)
      ->setPolicies($current_policies)
      ->setSpacePHID($space)
      ->setName('viewPolicy');
    $edit_policies = id(new AphrontFormPolicyControl())
      ->setUser($viewer)
      ->setValue($edit_policy)
      ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
      ->setPolicyObject($url)
      ->setPolicies($current_policies)
      ->setName('editPolicy');

    $subscribers = id(new AphrontFormTokenizerControl())
      ->setLabel(pht('Subscribers'))
      ->setName('subscribers')
      ->setValue($subscribers)
      ->setUser($viewer)
      ->setDatasource(new PhabricatorMetaMTAMailableDatasource());

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($name)
      ->appendChild($long_url)
      ->appendControl($view_policies)
      ->appendControl($edit_policies)
      ->appendControl($subscribers)
      ->appendChild($projects)
      ->appendChild($description);


    if ($request->isAjax()) {
      return $this->newDialog()
        ->setTitle($page_title)
        ->setWidth(AphrontDialogView::WIDTH_FULL)
        ->appendForm($form)
        ->addCancelButton($cancel_uri)
        ->addSubmitButton($submit_label);
    }

    $submit = id(new AphrontFormSubmitControl())
      ->addCancelButton($cancel_uri)
      ->setValue($submit_label);

    $form->appendChild($submit);

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($page_title)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();

    if (!$is_create) {
      $crumbs->addTextCrumb($url->getMonogram(), $url->getURI());
    } else {
      $crumbs->addTextCrumb(pht('Create URL'));
    }

    $crumbs->addTextCrumb($page_title);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText($page_title)
      ->setValidationException($validation_exception)
      ->appendChild($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $page_title,
      ));
  }
}
