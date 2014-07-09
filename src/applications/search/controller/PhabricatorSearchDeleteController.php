<?php

final class PhabricatorSearchDeleteController
  extends PhabricatorSearchBaseController {

  private $queryKey;
  private $engineClass;

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
    $this->engineClass = idx($data, 'engine');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $key = $this->queryKey;

    $base_class = 'PhabricatorApplicationSearchEngine';
    if (!is_subclass_of($this->engineClass, $base_class)) {
      return new Aphront400Response();
    }

    $engine = newv($this->engineClass, array());
    $engine->setViewer($user);

    $named_query = id(new PhabricatorNamedQueryQuery())
      ->setViewer($user)
      ->withEngineClassNames(array($this->engineClass))
      ->withQueryKeys(array($key))
      ->withUserPHIDs(array($user->getPHID()))
      ->executeOne();

    if (!$named_query && $engine->isBuiltinQuery($key)) {
      $named_query = $engine->getBuiltinQuery($key);
    }

    if (!$named_query) {
      return new Aphront404Response();
    }

    $builtin = null;
    if ($engine->isBuiltinQuery($key)) {
      $builtin = $engine->getBuiltinQuery($key);
    }

    $return_uri = $engine->getQueryManagementURI();

    if ($request->isDialogFormPost()) {
      if ($named_query->getIsBuiltin()) {
        $named_query->setIsDisabled((int)(!$named_query->getIsDisabled()));
        $named_query->save();
      } else {
        $named_query->delete();
      }

      return id(new AphrontRedirectResponse())->setURI($return_uri);
    }

    if ($named_query->getIsBuiltin()) {
      if ($named_query->getIsDisabled()) {
        $title = pht('Enable Query?');
        $desc = pht(
          'Enable the built-in query "%s"? It will appear in your menu again.',
          $builtin->getQueryName());
        $button = pht('Enable Query');
      } else {
        $title = pht('Disable Query?');
        $desc = pht(
          'This built-in query can not be deleted, but you can disable it so '.
          'it does not appear in your query menu. You can enable it again '.
          'later. Disable built-in query "%s"?',
          $builtin->getQueryName());
        $button = pht('Disable Query');
      }
    } else {
      $title = pht('Really Delete Query?');
      $desc = pht(
        'Really delete the query "%s"? You can not undo this. Remember '.
        'all the great times you had filtering results together?',
        $named_query->getQueryName());
      $button = pht('Delete Query');
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle($title)
      ->appendChild($desc)
      ->addCancelButton($return_uri)
      ->addSubmitButton($button);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
