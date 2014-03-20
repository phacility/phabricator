<?php

final class PhabricatorProjectMembersEditController
  extends PhabricatorProjectController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->needMembers(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
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

      $type_member = PhabricatorEdgeConfig::TYPE_PROJ_MEMBER;

      $xactions = array();

      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $type_member)
        ->setNewValue($member_spec);

      $editor = id(new PhabricatorProjectTransactionEditor($project))
        ->setActor($user)
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

    $header_name = pht('Edit Members');
    $title = pht('Edit Members');

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('phids')
          ->setLabel(pht('Add Members'))
          ->setDatasource('/typeahead/common/accounts/'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/project/view/'.$project->getID().'/')
          ->setValue(pht('Add Members')));

    $member_list = $this->renderMemberList($project, $handles);

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView())
      ->addTextCrumb(
        $project->getName(),
        '/project/view/'.$project->getID().'/')
      ->addTextCrumb(pht('Edit Members'), $this->getApplicationURI());

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
        $member_list,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function renderMemberList(
    PhabricatorProject $project,
    array $handles) {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $list = id(new PHUIObjectItemListView())
      ->setNoDataString(pht('This project does not have any members.'));

    foreach ($handles as $handle) {
      $remove_uri = $this->getApplicationURI(
        '/members/'.$project->getID().'/remove/?phid='.$handle->getPHID());

      $item = id(new PHUIObjectItemView())
        ->setHeader($handle->getFullName())
        ->setHref($handle->getURI())
        ->setImageURI($handle->getImageURI());

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('delete')
          ->setName(pht('Remove'))
          ->setHref($remove_uri)
          ->setWorkflow(true));

      $list->addItem($item);
    }

    return $list;
  }
}
