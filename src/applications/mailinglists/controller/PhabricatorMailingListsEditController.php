<?php

final class PhabricatorMailingListsEditController
  extends PhabricatorMailingListsController {

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

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());

    $request = $this->getRequest();
    if ($request->isFormPost()) {
      $list->setName($request->getStr('name'));
      $list->setEmail($request->getStr('email'));
      $list->setURI($request->getStr('uri'));

      $e_email = null;
      $e_name = null;

      if (!strlen($list->getEmail())) {
        $e_email = pht('Required');
        $errors[] = pht('Email is required.');
      }

      if (!strlen($list->getName())) {
        $e_name = pht('Required');
        $errors[] = pht('Name is required.');
      } else if (preg_match('/[ ,]/', $list->getName())) {
        $e_name = pht('Invalid');
        $errors[] = pht('Name must not contain spaces or commas.');
      }

      if ($list->getURI()) {
        if (!PhabricatorEnv::isValidWebResource($list->getURI())) {
          $e_uri = pht('Invalid');
          $errors[] = pht('Mailing list URI must point to a valid web page.');
        }
      }

      if (!$errors) {
        try {
          $list->save();
          return id(new AphrontRedirectResponse())
            ->setURI($this->getApplicationURI());
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $e_email = pht('Duplicate');
          $errors[] = pht('Another mailing list already uses that address.');
        }
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('Form Errors'))
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
          ->setLabel(pht('Email'))
          ->setName('email')
          ->setValue($list->getEmail())
          ->setCaption(pht('Email will be delivered to this address.'))
          ->setError($e_email))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setError($e_name)
          ->setCaption(pht('Human-readable display and autocomplete name.'))
          ->setValue($list->getName()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('URI'))
          ->setName('uri')
          ->setError($e_uri)
          ->setCaption(pht('Optional link to mailing list archives or info.'))
          ->setValue($list->getURI()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('PHID')
          ->setValue(nonempty($list->getPHID(), '-')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save'))
          ->addCancelButton($this->getApplicationURI()));

    if ($list->getID()) {
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Edit Mailing List'))
          ->setHref($this->getApplicationURI('/edit/'.$list->getID().'/')));
    } else {
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Create Mailing List'))
          ->setHref($this->getApplicationURI('/edit/')));
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $error_view,
        $form,
      ),
      array(
        'title' => pht('Edit Mailing List'),
        'device' => true,
        'dust' => true,
      ));
  }

}
