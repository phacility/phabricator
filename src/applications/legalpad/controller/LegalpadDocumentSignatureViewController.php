<?php

final class LegalpadDocumentSignatureViewController extends LegalpadController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $signature = id(new LegalpadDocumentSignatureQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$signature) {
      return new Aphront404Response();
    }


    // NOTE: In order to see signature details (which include the relatively
    // internal-feeling "notes" field) you must be able to edit the document.
    // Essentially, this power is for document managers. Notably, this prevents
    // users from seeing notes about their own exemptions by guessing their
    // signature ID. This is purely a policy check.

    $document = id(new LegalpadDocumentQuery())
      ->setViewer($viewer)
      ->withIDs(array($signature->getDocument()->getID()))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$document) {
      return new Aphront404Response();
    }


    $document_id = $signature->getDocument()->getID();
    $next_uri = $this->getApplicationURI('signatures/'.$document_id.'/');

    $exemption_phid = $signature->getExemptionPHID();
    $handles = $this->loadViewerHandles(array($exemption_phid));
    $exemptor_handle = $handles[$exemption_phid];

    $data = $signature->getSignatureData();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Exemption By'))
          ->setValue($exemptor_handle->renderLink()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Notes'))
          ->setValue(idx($data, 'notes')));

    return $this->newDialog()
      ->setTitle(pht('Signature Details'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($form->buildLayoutView())
      ->addCancelButton($next_uri, pht('Close'));
  }

}
