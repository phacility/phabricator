<?php

/**
 * @group search
 */
final class PhabricatorSearchNameController
  extends PhabricatorSearchBaseController {

  private $queryKey;

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($this->queryKey) {
      $saved_query = id(new PhabricatorSavedQueryQuery())
        ->setViewer($user)
        ->withQueryKeys(array($this->queryKey))
        ->executeOne();
      if (!$saved_query) {
        return new Aphront404Response();
      }
    } else {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $named_query = id(new PhabricatorNamedQuery())
        ->setUserPHID($user->getPHID())
        ->setQueryKey($saved_query->getQueryKey())
        ->setQueryName($request->getStr('name'))
        ->setEngineClassName($saved_query->getEngineClassName());

      try {
        $named_query->save();
      } catch (AphrontQueryDuplicateKeyException $ex) {
        // Ignore, the user is naming an identical query.
      }

      return id(new AphrontRedirectResponse())
        ->setURI('/search/');
    }

    $form = id(new AphrontFormView())
      ->setUser($user);

    $form->appendChild(
      id(new AphrontFormTextControl())
        ->setName('name')
        ->setLabel(pht('Query Name')));

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save')));

    return $this->buildStandardPageResponse(
      array(
        $form,
      ),
      array(
        'title' => 'Name Query',
      ));
  }


}
