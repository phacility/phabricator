<?php

final class PhabricatorSpacesArchiveController
  extends PhabricatorSpacesController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $space = id(new PhabricatorSpacesNamespaceQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$space) {
      return new Aphront404Response();
    }

    $is_archive = ($request->getURIData('action') == 'archive');
    $cancel_uri = '/'.$space->getMonogram();

    if ($request->isFormPost()) {
      $type_archive = PhabricatorSpacesNamespaceTransaction::TYPE_ARCHIVE;

      $xactions = array();
      $xactions[] = id(new PhabricatorSpacesNamespaceTransaction())
        ->setTransactionType($type_archive)
        ->setNewValue($is_archive ? 1 : 0);

      $editor = id(new PhabricatorSpacesNamespaceEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request);

      $editor->applyTransactions($space, $xactions);

      return id(new AphrontRedirectResponse())->setURI($cancel_uri);
    }

    $body = array();
    if ($is_archive) {
      $title = pht('Archive Space: %s', $space->getNamespaceName());
      $body[] = pht(
        'If you archive this Space, you will no longer be able to create '.
        'new objects inside it.');
      $body[] = pht(
        'Existing objects in this Space will be hidden from query results '.
        'by default.');
      $button = pht('Archive Space');
    } else {
      $title = pht('Activate Space: %s', $space->getNamespaceName());
      $body[] = pht(
        'If you activate this space, you will be able to create objects '.
        'inside it again.');
      $body[] = pht(
        'Existing objects will no longer be hidden from query results.');
      $button = pht('Activate Space');
    }


    $dialog = $this->newDialog()
      ->setTitle($title)
      ->addCancelButton($cancel_uri)
      ->addSubmitButton($button);

    foreach ($body as $paragraph) {
      $dialog->appendParagraph($paragraph);
    }

    return $dialog;
  }
}
