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
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }
    $profile = $project->loadProfile();
    if (empty($profile)) {
      $profile = new PhabricatorProjectProfile();
    }

    $member_phids = $project->loadMemberPHIDs();

    $errors = array();
    if ($request->isFormPost()) {
      $changed_something = false;
      $member_map = array_fill_keys($member_phids, true);

      $remove = $request->getStr('remove');
      if ($remove) {
        if (isset($member_map[$remove])) {
          unset($member_map[$remove]);
          $changed_something = true;
        }
      } else {
        $new_members = $request->getArr('phids');
        foreach ($new_members as $member) {
          if (empty($member_map[$member])) {
            $member_map[$member] = true;
            $changed_something = true;
          }
        }
      }

      $xactions = array();
      if ($changed_something) {
        $xaction = new PhabricatorProjectTransaction();
        $xaction->setTransactionType(
          PhabricatorProjectTransactionType::TYPE_MEMBERS);
        $xaction->setNewValue(array_keys($member_map));
        $xactions[] = $xaction;
      }

      if ($xactions) {
        $editor = new PhabricatorProjectEditor($project);
        $editor->setActor($user);
        $editor->applyTransactions($xactions);
      }

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

    $list = $this->renderMemberList($handles);

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('phids')
          ->setLabel(pht('Add Members'))
          ->setDatasource('/typeahead/common/users/'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/project/view/'.$project->getID().'/')
          ->setValue(pht('Add Members')));
    $faux_form = id(new AphrontFormLayoutView())
      ->setBackgroundShading(true)
      ->setPadded(true)
      ->appendChild(
        id(new AphrontFormInsetView())
          ->setTitle(pht('Current Members (%d)', count($handles)))
          ->appendChild($list));

    $panel = new AphrontPanelView();
    $panel->setHeader($header_name);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setNoBackground();
    $panel->appendChild($form);
    $panel->appendChild(phutil_tag('br'));
    $panel->appendChild($faux_form);

    $nav = $this->buildLocalNavigation($project);
    $nav->selectFilter('members');
    $nav->appendChild($panel);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($project->getName())
        ->setHref('/project/view/'.$project->getID().'/'));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Edit Members'))
        ->setHref($this->getApplicationURI()));
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function renderMemberList(array $handles) {
    $request = $this->getRequest();
    $user = $request->getUser();
    $list = id(new PhabricatorObjectListView())
      ->setHandles($handles);

    foreach ($handles as $handle) {
      $hidden_input = phutil_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => 'remove',
          'value' => $handle->getPHID(),
        ),
        '');

      $button = javelin_tag(
        'button',
        array(
          'class' => 'grey',
        ),
        pht('Remove'));

      $list->addButton(
        $handle,
        phabricator_form(
          $user,
          array(
            'method' => 'POST',
            'action' => $request->getRequestURI(),
          ),
          array($hidden_input, $button)));
    }

    return $list;
  }
}
