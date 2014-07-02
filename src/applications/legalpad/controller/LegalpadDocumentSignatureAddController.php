<?php

final class LegalpadDocumentSignatureAddController extends LegalpadController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $document = id(new LegalpadDocumentQuery())
      ->setViewer($viewer)
      ->needDocumentBodies(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$document) {
      return new Aphront404Response();
    }

    $next_uri = $this->getApplicationURI('signatures/'.$document->getID().'/');

    $e_user = true;
    $v_users = array();
    $v_notes = '';
    $errors = array();

    if ($request->isFormPost()) {
      $v_notes = $request->getStr('notes');
      $v_users = array_slice($request->getArr('users'), 0, 1);

      $user_phid = head($v_users);
      if (!$user_phid) {
        $e_user = pht('Required');
        $errors[] = pht('You must choose a user to exempt.');
      } else {
        $user = id(new PhabricatorPeopleQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($user_phid))
          ->executeOne();

        if (!$user) {
          $e_user = pht('Invalid');
          $errors[] = pht('That user does not exist.');
        } else {
          $signature = id(new LegalpadDocumentSignatureQuery())
            ->setViewer($viewer)
            ->withDocumentPHIDs(array($document->getPHID()))
            ->withSignerPHIDs(array($user->getPHID()))
            ->executeOne();
          if ($signature) {
            $e_user = pht('Signed');
            $errors[] = pht('That user has already signed this document.');
          } else {
            $e_user = null;
          }
        }
      }

      if (!$errors) {
        $name = $user->getRealName();
        $email = $user->loadPrimaryEmailAddress();

        $signature = id(new LegalpadDocumentSignature())
          ->setDocumentPHID($document->getPHID())
          ->setDocumentVersion($document->getVersions())
          ->setSignerPHID($user->getPHID())
          ->setSignerName($name)
          ->setSignerEmail($email)
          ->setIsExemption(1)
          ->setExemptionPHID($viewer->getPHID())
          ->setVerified(LegalpadDocumentSignature::VERIFIED)
          ->setSignatureData(
            array(
              'name' => $name,
              'email' => $email,
              'notes' => $v_notes,
            ));

        $signature->save();

        return id(new AphrontRedirectResponse())->setURI($next_uri);
      }
    }

    $user_handles = $this->loadViewerHandles($v_users);

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Exempt User'))
          ->setName('users')
          ->setLimit(1)
          ->setDatasource('/typeahead/common/users/')
          ->setValue($user_handles)
          ->setError($e_user))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Notes'))
          ->setName('notes')
          ->setValue($v_notes));

    return $this->newDialog()
      ->setTitle(pht('Add Signature Exemption'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setErrors($errors)
      ->appendParagraph(
        pht(
          'You can record a signature exemption if a user has signed an '.
          'equivalent document. Other applications will behave as through the '.
          'user has signed this document.'))
      ->appendParagraph(null)
      ->appendChild($form->buildLayoutView())
      ->addSubmitButton(pht('Add Exemption'))
      ->addCancelButton($next_uri);
  }

}
