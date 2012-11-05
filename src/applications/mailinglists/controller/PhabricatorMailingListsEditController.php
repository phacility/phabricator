<?php

final class PhabricatorMailingListsEditController
  extends PhabricatorController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    if ($this->id) {
      $list = id(new PhabricatorMetaMTAMailingList())->load($this->id);
      if (!$list) {
        return new Aphront404Response();
      }
    } else {
      $list = new PhabricatorMetaMTAMailingList();
    }

    $e_email = true;
    $e_uri = null;
    $e_name = true;
    $errors = array();

    $request = $this->getRequest();
    if ($request->isFormPost()) {
      $list->setName($request->getStr('name'));
      $list->setEmail($request->getStr('email'));
      $list->setURI($request->getStr('uri'));

      $e_email = null;
      $e_name = null;

      if (!strlen($list->getEmail())) {
        $e_email = 'Required';
        $errors[] = 'Email is required.';
      }

      if (!strlen($list->getName())) {
        $e_name = 'Required';
        $errors[] = 'Name is required.';
      } else if (preg_match('/[ ,]/', $list->getName())) {
        $e_name = 'Invalid';
        $errors[] = 'Name must not contain spaces or commas.';
      }

      if ($list->getURI()) {
        if (!PhabricatorEnv::isValidWebResource($list->getURI())) {
          $e_uri = 'Invalid';
          $errors[] = 'Mailing list URI must point to a valid web page.';
        }
      }

      if (!$errors) {
        try {
          $list->save();
          return id(new AphrontRedirectResponse())
            ->setURI($this->getApplicationURI());
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $e_email = 'Duplicate';
          $errors[] = 'Another mailing list already uses that address.';
        }
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }

    $form = new AphrontFormView();
    $form->setUser($request->getUser());
    if ($list->getID()) {
      $form->setAction($this->getApplicationURI('/edit/'.$list->getID().'/'));
    } else {
      $form->setAction($this->getApplicationURI('/edit/'));
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Email')
          ->setName('email')
          ->setValue($list->getEmail())
          ->setCaption('Email will be delivered to this address.')
          ->setError($e_email))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setError($e_name)
          ->setCaption('Human-readable display and autocomplete name.')
          ->setValue($list->getName()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('URI')
          ->setName('uri')
          ->setError($e_uri)
          ->setCaption('Optional link to mailing list archives or info.')
          ->setValue($list->getURI()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('PHID')
          ->setValue(nonempty($list->getPHID(), '-')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save')
          ->addCancelButton($this->getApplicationURI()));

    $panel = new AphrontPanelView();
    if ($list->getID()) {
      $panel->setHeader('Edit Mailing List');
    } else {
      $panel->setHeader('Create New Mailing List');
    }

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildApplicationPage(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => 'Edit Mailing List',
      ));
  }

}
