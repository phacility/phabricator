<?php

final class PhabricatorFileEditController extends PhabricatorFileController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    $title = pht('Edit %s', $file->getName());
    $view_uri = '/'.$file->getMonogram();

    $validation_exception = null;
    if ($request->isFormPost()) {
      $can_view = $request->getStr('canView');

      $xactions = array();

      $xactions[] = id(new PhabricatorFileTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
        ->setNewValue($can_view);

      $editor = id(new PhabricatorFileEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($file, $xactions);
        return id(new AphrontRedirectResponse())->setURI($view_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

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
      ->addTextCrumb(pht('Edit'));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setValidationException($validation_exception)
      ->appendChild($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $title,
      ));
  }

}
