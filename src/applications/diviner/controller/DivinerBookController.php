<?php

final class DivinerBookController extends DivinerController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $book_name = $request->getURIData('book');

    $book = id(new DivinerBookQuery())
      ->setViewer($viewer)
      ->withNames(array($book_name))
      ->needRepositories(true)
      ->executeOne();

    if (!$book) {
      return new Aphront404Response();
    }

    $actions = $this->buildActionView($viewer, $book);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);
    $crumbs->addTextCrumb(
      $book->getShortTitle(),
      '/book/'.$book->getName().'/');

    $header = id(new PHUIHeaderView())
      ->setHeader($book->getTitle())
      ->setUser($viewer)
      ->setPolicyObject($book)
      ->setEpoch($book->getDateModified())
      ->setActionList($actions);

    // TODO: This could probably look better.
    if ($book->getRepositoryPHID()) {
      $header->addTag(
        id(new PHUITagView())
          ->setType(PHUITagView::TYPE_STATE)
          ->setBackgroundColor(PHUITagView::COLOR_BLUE)
          ->setName($book->getRepository()->getMonogram()));
    }

    $document = new PHUIDocumentViewPro();
    $document->setHeader($header);
    $document->addClass('diviner-view');

    $atoms = id(new DivinerAtomQuery())
      ->setViewer($viewer)
      ->withBookPHIDs(array($book->getPHID()))
      ->withGhosts(false)
      ->withIsDocumentable(true)
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
      $preface_view = new PHUIRemarkupView($viewer, $preface);
    }

    $document->appendChild($preface_view);
    $document->appendChild($out);

    return $this->newPage()
      ->setTitle($book->getTitle())
      ->setCrumbs($crumbs)
      ->appendChild(array(
        $document,
      ));
  }

  private function buildActionView(
    PhabricatorUser $user,
    DivinerLiveBook $book) {

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $book,
      PhabricatorPolicyCapability::CAN_EDIT);

    $action_view = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObject($book);

    $action_view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Book'))
        ->setIcon('fa-pencil')
        ->setHref('/book/'.$book->getName().'/edit/')
        ->setDisabled(!$can_edit));

    return $action_view;
  }

}
