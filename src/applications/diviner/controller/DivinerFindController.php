<?php

final class DivinerFindController extends DivinerController {

  public function shouldAllowPublic() {
    return true;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $book_name = $request->getStr('book');

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

    $name_query = clone $query;

    $name_query->withNames(
      array(
        $request->getStr('name'),
        // TODO: This could probably be more smartly normalized in the DB,
        // but just fake it for now.
        phutil_utf8_strtolower($request->getStr('name')),
      ));

    $atoms = $name_query->execute();

    if (!$atoms) {
      $title_query = clone $query;
      $title_query->withTitles(array($request->getStr('name')));
      $atoms = $title_query->execute();
    }

    if (!$atoms) {
      return new Aphront404Response();
    }

    if (count($atoms) == 1 && $request->getBool('jump')) {
      $atom_uri = head($atoms)->getURI();
      return id(new AphrontRedirectResponse())->setURI($atom_uri);
    }

    $list = $this->renderAtomList($atoms);

    return $this->buildApplicationPage(
      $list,
      array(
        'title' => 'derp',
        'device' => true,
      ));
  }

}
