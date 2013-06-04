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

    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($book->getTitle())
        ->setHref('/book/'.$book->getName().'/'));

    $header = id(new PhabricatorHeaderView())->setHeader($book->getTitle());
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
      $group_info = idx($group_spec, $group);
      if (!is_array($group_info)) {
        $group_info = array();
      }

      $group_name = idx($group_info, 'name');
      if (!strlen($group_name)) {
        if (strlen($group)) {
          $group_name = $group;
        } else {
          $group_name = pht('Free Radicals');
        }
      }

      $out[] = id(new PhabricatorHeaderView())
        ->setHeader($group_name);
      $out[] = $this->renderAtomList($atoms);
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $properties,
        $out,
      ),
      array(
        'title' => $book->getTitle(),
        'dust' => true,
        'device' => true,
      ));
  }

  private function buildPropertyList(DivinerLiveBook $book) {
    $user = $this->getRequest()->getUser();
    $view = id(new PhabricatorPropertyListView())
      ->setUser($user);

    $policies = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $user,
      $book);

    $view->addProperty(
      pht('Visible To'),
      $policies[PhabricatorPolicyCapability::CAN_VIEW]);

    $view->addProperty(
      pht('Updated'),
      phabricator_datetime($book->getDateModified(), $user));

    return $view;
  }

}
