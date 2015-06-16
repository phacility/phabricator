<?php

final class DivinerFindController extends DivinerController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $book_name  = $request->getStr('book');
    $query_text = $request->getStr('name');

    $book = null;
    if ($book_name) {
      $book = id(new DivinerBookQuery())
        ->setViewer($viewer)
        ->withNames(array($book_name))
        ->executeOne();

      if (!$book) {
        return new Aphront404Response();
      }
    }

    $query = id(new DivinerAtomQuery())
      ->setViewer($viewer);

    if ($book) {
      $query->withBookPHIDs(array($book->getPHID()));
    }

    $context = $request->getStr('context');
    if (strlen($context)) {
      $query->withContexts(array($context));
    }

    $type = $request->getStr('type');
    if (strlen($type)) {
      $query->withTypes(array($type));
    }

    $query->withGhosts(false);
    $query->withIsDocumentable(true);

    $name_query = clone $query;

    $name_query->withNames(
      array(
        $query_text,
        // TODO: This could probably be more smartly normalized in the DB,
        // but just fake it for now.
        phutil_utf8_strtolower($query_text),
      ));

    $atoms = $name_query->execute();

    if (!$atoms) {
      $title_query = clone $query;
      $title_query->withTitles(array($query_text));
      $atoms = $title_query->execute();
    }

    $not_found_uri = $this->getApplicationURI();

    if (!$atoms) {
      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setTitle(pht('Documentation Not Found'))
        ->appendChild(
          pht(
            'Unable to find the specified documentation. '.
            'You may have followed a bad or outdated link.'))
        ->addCancelButton($not_found_uri, pht('Read More Documentation'));

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    if (count($atoms) == 1 && $request->getBool('jump')) {
      $atom_uri = head($atoms)->getURI();
      return id(new AphrontRedirectResponse())->setURI($atom_uri);
    }

    $list = $this->renderAtomList($atoms);

    return $this->buildApplicationPage(
      $list,
      array(
        'title' => array(pht('Find'), pht('"%s"', $query_text)),
      ));
  }

}
