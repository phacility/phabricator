<?php

final class LegalpadDocumentSignatureViewController extends LegalpadController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $signature = id(new LegalpadDocumentSignatureQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
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

    $data = $signature->getSignatureData();

    $exemption_phid = $signature->getExemptionPHID();
    $actor_phid = idx($data, 'actorPHID');
    $handles = $this->loadViewerHandles(
      array(
        $exemption_phid,
        $actor_phid,
      ));
    $exemptor_handle = $handles[$exemption_phid];
    $actor_handle = $handles[$actor_phid];

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    if ($signature->getExemptionPHID()) {
      $form
        ->appendChild(
          id(new AphrontFormMarkupControl())
            ->setLabel(pht('Exemption By'))
            ->setValue($exemptor_handle->renderLink()))
        ->appendChild(
          id(new AphrontFormMarkupControl())
            ->setLabel(pht('Notes'))
            ->setValue(idx($data, 'notes')));
    }

    $type_corporation = LegalpadDocument::SIGNATURE_TYPE_CORPORATION;
    if ($signature->getSignatureType() == $type_corporation) {
      $form
        ->appendChild(
          id(new AphrontFormMarkupControl())
            ->setLabel(pht('Signing User'))
            ->setValue($actor_handle->renderLink()))
        ->appendChild(
          id(new AphrontFormMarkupControl())
            ->setLabel(pht('Company Name'))
            ->setValue(idx($data, 'name')))
        ->appendChild(
          id(new AphrontFormMarkupControl())
            ->setLabel(pht('Address'))
            ->setValue(phutil_escape_html_newlines(idx($data, 'address'))))
        ->appendChild(
          id(new AphrontFormMarkupControl())
            ->setLabel(pht('Contact Name'))
            ->setValue(idx($data, 'contact.name')))
        ->appendChild(
          id(new AphrontFormMarkupControl())
            ->setLabel(pht('Contact Email'))
            ->setValue(
              phutil_tag(
                'a',
                array(
                  'href' => 'mailto:'.idx($data, 'email'),
                ),
                idx($data, 'email'))));
    }

    return $this->newDialog()
      ->setTitle(pht('Signature Details'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($form->buildLayoutView())
      ->addCancelButton($next_uri, pht('Close'));
  }

}
