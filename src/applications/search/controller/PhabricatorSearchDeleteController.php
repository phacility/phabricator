<?php

final class PhabricatorSearchDeleteController
  extends PhabricatorSearchBaseController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $key = $request->getURIData('queryKey');
    $engine_class = $request->getURIData('engine');

    $base_class = 'PhabricatorApplicationSearchEngine';
    if (!is_subclass_of($engine_class, $base_class)) {
      return new Aphront400Response();
    }

    $engine = newv($engine_class, array());
    $engine->setViewer($viewer);

    $named_query = id(new PhabricatorNamedQueryQuery())
      ->setViewer($viewer)
      ->withEngineClassNames(array($engine_class))
      ->withQueryKeys(array($key))
      ->withUserPHIDs(array($viewer->getPHID()))
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
      ->setUser($viewer)
      ->setTitle($title)
      ->appendChild($desc)
      ->addCancelButton($return_uri)
      ->addSubmitButton($button);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
