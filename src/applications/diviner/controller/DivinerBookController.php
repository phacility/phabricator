<?php

final class DivinerBookController extends DivinerController {

  private $bookName;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->bookName = $data['book'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $book = id(new DivinerBookQuery())
      ->setViewer($viewer)
      ->withNames(array($this->bookName))
      ->executeOne();

    if (!$book) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();

    $crumbs->addTextCrumb(
      $book->getShortTitle(),
      '/book/'.$book->getName().'/');

    $header = id(new PHUIHeaderView())
      ->setHeader($book->getTitle())
      ->setUser($viewer)
      ->setPolicyObject($book);

    $document = new PHUIDocumentView();
    $document->setHeader($header);
    $document->setFontKit(PHUIDocumentView::FONT_SOURCE_SANS);

    $properties = $this->buildPropertyList($book);

    $atoms = id(new DivinerAtomQuery())
      ->setViewer($viewer)
      ->withBookPHIDs(array($book->getPHID()))
      ->execute();

    $atoms = msort($atoms, 'getSortKey');

    $group_spec = $book->getConfig('groups');
    if (!is_array($group_spec)) {
      $group_spec = array();
    }

    $groups = mgroup($atoms, 'getGroupName');
    $groups = array_select_keys($groups, array_keys($group_spec)) + $groups;
    if (isset($groups[''])) {
      $no_group = $groups[''];
      unset($groups['']);
      $groups[''] = $no_group;
    }

    $out = array();
    foreach ($groups as $group => $atoms) {
      $group_name = $book->getGroupName($group);
      if (!strlen($group_name)) {
        $group_name = pht('Free Radicals');
      }
      $section = id(new DivinerSectionView())
        ->setHeader($group_name);
      $section->addContent($this->renderAtomList($atoms));
      $out[] = $section;
    }

    $preface = $book->getPreface();
    $preface_view = null;
    if (strlen($preface)) {
      $preface_view =
        PhabricatorMarkupEngine::renderOneObject(
          id(new PhabricatorMarkupOneOff())->setContent($preface),
          'default',
          $viewer);
    }

    $document->appendChild($properties);
    $document->appendChild($preface_view);
    $document->appendChild($out);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $document,
      ),
      array(
        'title' => $book->getTitle(),
        'device' => true,
      ));
  }

  private function buildPropertyList(DivinerLiveBook $book) {
    $viewer = $this->getRequest()->getUser();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $policies = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $book);

    $view->addProperty(
      pht('Updated'),
      phabricator_datetime($book->getDateModified(), $viewer));

    return $view;
  }

}
