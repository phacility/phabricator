<?php

final class PhabricatorProjectMembersEditController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needMembers(true)
      ->needImages(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $member_phids = $project->getMemberPHIDs();

    if ($request->isFormPost()) {
      $member_spec = array();

      $remove = $request->getStr('remove');
      if ($remove) {
        $member_spec['-'] = array_fuse(array($remove));
      }

      $add_members = $request->getArr('phids');
      if ($add_members) {
        $member_spec['+'] = array_fuse($add_members);
      }

      $type_member = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;

      $xactions = array();

      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $type_member)
        ->setNewValue($member_spec);

      $editor = id(new PhabricatorProjectTransactionEditor($project))
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($project, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($request->getRequestURI());
    }

    $member_phids = array_reverse($member_phids);
    $handles = $this->loadViewerHandles($member_phids);

    $state = array();
    foreach ($handles as $handle) {
      $state[] = array(
        'phid' => $handle->getPHID(),
        'name' => $handle->getFullName(),
      );
    }

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $form_box = null;
    $title = pht('Add Members');
    if ($can_edit) {
      $header_name = pht('Edit Members');
      $view_uri = $this->getApplicationURI('profile/'.$project->getID().'/');

      $form = new AphrontFormView();
      $form
        ->setUser($viewer)
        ->appendControl(
          id(new AphrontFormTokenizerControl())
            ->setName('phids')
            ->setLabel(pht('Add Members'))
            ->setDatasource(new PhabricatorPeopleDatasource()))
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->addCancelButton($view_uri)
            ->setValue(pht('Add Members')));
      $form_box = id(new PHUIObjectBoxView())
        ->setHeaderText($title)
        ->setForm($form);
    }

    $member_list = $this->renderMemberList($project, $handles);

    $nav = $this->buildIconNavView($project);
    $nav->selectFilter("members/{$id}/");
    $nav->appendChild($form_box);
    $nav->appendChild($member_list);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }

  private function renderMemberList(
    PhabricatorProject $project,
    array $handles) {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $list = id(new PHUIObjectItemListView())
      ->setNoDataString(pht('This project does not have any members.'));

    foreach ($handles as $handle) {
      $remove_uri = $this->getApplicationURI(
        '/members/'.$project->getID().'/remove/?phid='.$handle->getPHID());

      $item = id(new PHUIObjectItemView())
        ->setHeader($handle->getFullName())
        ->setHref($handle->getURI())
        ->setImageURI($handle->getImageURI());

      if ($can_edit) {
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-times')
            ->setName(pht('Remove'))
            ->setHref($remove_uri)
            ->setWorkflow(true));
      }

      $list->addItem($item);
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Members'))
      ->setObjectList($list);

    return $box;
  }
}
