<?php

final class PhabricatorRoleManageController
  extends PhabricatorRoleController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadRole();
    if ($response) {
      return $response;
    }

    $viewer = $request->getUser();
    $role = $this->getRole();
    $id = $role->getID();
    $picture = $role->getProfileImageURI();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Role History'))
      ->setUser($viewer)
      ->setPolicyObject($role);

    if ($role->getStatus() == PhabricatorRoleStatus::STATUS_ACTIVE) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'red', pht('Archived'));
    }

    $curtain = $this->buildCurtain($role);
    $properties = $this->buildPropertyListView($role);

    $timeline = $this->buildTransactionTimeline(
      $role,
      new PhabricatorRoleTransactionQuery());
    $timeline->setShouldTerminate(true);

    $nav = $this->newNavigation(
      $role,
      PhabricatorRole::ITEM_MANAGE);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Manage'));
    $crumbs->setBorder(true);

    require_celerity_resource('project-view-css');

    $manage = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->addPropertySection(pht('Details'), $properties)
      ->addClass('project-view-home')
      ->addClass('project-view-people-home')
      ->setMainColumn(
        array(
          $timeline,
        ));

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle(
        array(
          $role->getDisplayName(),
          pht('Manage'),
        ))
      ->appendChild(
        array(
          $manage,
        ));
  }

  private function buildCurtain(PhabricatorRole $role) {
    $viewer = $this->getViewer();

    $id = $role->getID();
    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $role,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($role);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Details'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Menu'))
        ->setIcon('fa-th-list')
        ->setHref($this->getApplicationURI("{$id}/item/configure/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Picture'))
        ->setIcon('fa-picture-o')
        ->setHref($this->getApplicationURI("picture/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($role->isArchived()) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Activate Role'))
          ->setIcon('fa-check')
          ->setHref($this->getApplicationURI("archive/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Archive Role'))
          ->setIcon('fa-ban')
          ->setHref($this->getApplicationURI("archive/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    }

    return $curtain;
  }

  private function buildPropertyListView(
    PhabricatorRole $role) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $view->addProperty(
      pht('Looks Like'),
      $viewer->renderHandle($role->getPHID())->setAsTag(true));

    $slugs = $role->getSlugs();
    $tags = mpull($slugs, 'getSlug');

    $view->addProperty(
      pht('Hashtags'),
      $this->renderHashtags($tags));

    $field_list = PhabricatorCustomField::getObjectFields(
      $role,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list->appendFieldsToPropertyList($role, $viewer, $view);

    return $view;
  }

}
