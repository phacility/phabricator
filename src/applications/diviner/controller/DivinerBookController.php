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

    $action_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Actions'))
      ->setHref('#')
      ->setIconFont('fa-bars')
      ->addClass('phui-mobile-menu')
      ->setDropdownMenu($actions);

    $header = id(new PHUIHeaderView())
      ->setHeader($book->getTitle())
      ->setUser($viewer)
      ->setPolicyObject($book)
      ->setEpoch($book->getDateModified())
      ->addActionLink($action_button);

    $document = new PHUIDocumentView();
    $document->setHeader($header);
    $document->addClass('diviner-view');
    $document->setFontKit(PHUIDocumentView::FONT_SOURCE_SANS);

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
      $preface_view =
        PhabricatorMarkupEngine::renderOneObject(
          id(new PhabricatorMarkupOneOff())->setContent($preface),
          'default',
          $viewer);
    }

    $document->appendChild($preface_view);
    $document->appendChild($out);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $document,
      ),
      array(
        'title' => $book->getTitle(),
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
      ->setObject($book)
      ->setObjectURI($this->getRequest()->getRequestURI());

    $action_view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Book'))
        ->setIcon('fa-pencil')
        ->setHref('/book/'.$book->getName().'/edit/')
        ->setDisabled(!$can_edit));

    return $action_view;
  }

}
