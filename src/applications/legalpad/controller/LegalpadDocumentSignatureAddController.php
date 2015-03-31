<?php

final class LegalpadDocumentSignatureAddController extends LegalpadController {

  public function handleRequest(AphrontRequest $request) {
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
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$document) {
      return new Aphront404Response();
    }

    $next_uri = $this->getApplicationURI('signatures/'.$document->getID().'/');

    $e_name = true;
    $e_user = true;
    $v_users = array();
    $v_notes = '';
    $v_name = '';
    $errors = array();

    $type_individual = LegalpadDocument::SIGNATURE_TYPE_INDIVIDUAL;
    $is_individual = ($document->getSignatureType() == $type_individual);

    if ($request->isFormPost()) {
      $v_notes = $request->getStr('notes');
      $v_users = array_slice($request->getArr('users'), 0, 1);
      $v_name = $request->getStr('name');

      if ($is_individual) {
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
      } else {
        $company_name = $v_name;
        if (!strlen($company_name)) {
          $e_name = pht('Required');
          $errors[] = pht('You must choose a company to add an exemption for.');
        }
      }

      if (!$errors) {
        if ($is_individual) {
          $name = $user->getRealName();
          $email = $user->loadPrimaryEmailAddress();
          $signer_phid = $user->getPHID();
          $signature_data = array(
            'name' => $name,
            'email' => $email,
            'notes' => $v_notes,
          );
        } else {
          $name = $company_name;
          $email = '';
          $signer_phid = null;
          $signature_data = array(
            'name' => $name,
            'email' => null,
            'notes' => $v_notes,
            'actorPHID' => $viewer->getPHID(),
          );
        }

        $signature = id(new LegalpadDocumentSignature())
          ->setDocumentPHID($document->getPHID())
          ->setDocumentVersion($document->getVersions())
          ->setSignerPHID($signer_phid)
          ->setSignerName($name)
          ->setSignerEmail($email)
          ->setSignatureType($document->getSignatureType())
          ->setIsExemption(1)
          ->setExemptionPHID($viewer->getPHID())
          ->setVerified(LegalpadDocumentSignature::VERIFIED)
          ->setSignatureData($signature_data);

        $signature->save();

        return id(new AphrontRedirectResponse())->setURI($next_uri);
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    if ($is_individual) {
      $form
        ->appendControl(
          id(new AphrontFormTokenizerControl())
            ->setLabel(pht('Exempt User'))
            ->setName('users')
            ->setLimit(1)
            ->setDatasource(new PhabricatorPeopleDatasource())
            ->setValue($v_users)
            ->setError($e_user));
    } else {
      $form
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setLabel(pht('Company Name'))
            ->setName('name')
            ->setError($e_name)
            ->setValue($v_name));
    }

    $form
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
