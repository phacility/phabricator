<?php

final class PhabricatorFileEditController extends PhabricatorFileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    $title = pht('Edit File: %s', $file->getName());
    $file_name = $file->getName();
    $header_icon = 'fa-pencil';
    $view_uri = '/'.$file->getMonogram();
    $error_name = true;
    $validation_exception = null;

    if ($request->isFormPost()) {
      $can_view = $request->getStr('canView');
      $file_name = $request->getStr('name');
      $errors = array();

      $type_name = PhabricatorFileTransaction::TYPE_NAME;

      $xactions = array();

      $xactions[] = id(new PhabricatorFileTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
        ->setNewValue($can_view);

      $xactions[] = id(new PhabricatorFileTransaction())
        ->setTransactionType(PhabricatorFileTransaction::TYPE_NAME)
        ->setNewValue($file_name);

      $editor = id(new PhabricatorFileEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($file, $xactions);
        return id(new AphrontRedirectResponse())->setURI($view_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $error_name = $ex->getShortMessage($type_name);

        $file->setViewPolicy($can_view);
      }
    }


    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($file)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
       ->appendChild(
        id(new AphrontFormTextControl())
        ->setName('name')
        ->setValue($file_name)
        ->setLabel(pht('Name'))
        ->setError($error_name))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($viewer)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicyObject($file)
          ->setPolicies($policies)
          ->setName('canView'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($view_uri)
          ->setValue(pht('Save Changes')));

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($file->getMonogram(), $view_uri)
      ->addTextCrumb(pht('Edit'))
      ->setBorder(true);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setValidationException($validation_exception)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($form);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon($header_icon);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($box);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
