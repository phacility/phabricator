<?php

/**
 * @group maniphest
 */
final class ManiphestSavedQueryEditController extends ManiphestController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $key = $request->getStr('key');
    if (!$key) {
      $id = nonempty($this->id, $request->getInt('id'));
      if (!$id) {
        return new Aphront404Response();
      }
      $query = id(new ManiphestSavedQuery())->load($id);
      if (!$query) {
        return new Aphront404Response();
      }
      if ($query->getUserPHID() != $user->getPHID()) {
        return new Aphront400Response();
      }
    } else {
      $query = new ManiphestSavedQuery();
      $query->setUserPHID($user->getPHID());
      $query->setQueryKey($key);
      $query->setIsDefault(0);
    }

    $e_name = true;
    $errors = array();

    if ($request->isFormPost()) {
      $e_name = null;
      $query->setName($request->getStr('name'));
      if (!strlen($query->getName())) {
        $e_name = pht('Required');
        $errors[] = pht('Saved query name is required.');
      }

      if (!$errors) {
        $query->save();
        return id(new AphrontRedirectResponse())->setURI('/maniphest/custom/');
      }
    }

    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle(pht('Form Errors'));
      $error_view->setErrors($errors);
    } else {
      $error_view = null;
    }

    if ($query->getID()) {
      $header = pht('Edit Saved Query');
      $cancel_uri = '/maniphest/custom/';
    } else {
      $header = pht('New Saved Query');
      $cancel_uri = '/maniphest/view/custom/?key='.$key;
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->addHiddenInput('key', $key)
      ->addHiddenInput('id',  $query->getID())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setValue($query->getName())
          ->setName('name')
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue(pht('Save')));

    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    $nav = $this->buildBaseSideNav();
    // The side nav won't show "Saved Queries..." until you have at least one.
    $nav->selectFilter('saved', 'custom');
    $nav->appendChild($error_view);
    $nav->appendChild($panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Saved Queries'),
        'device' => true,
      ));
  }

}
