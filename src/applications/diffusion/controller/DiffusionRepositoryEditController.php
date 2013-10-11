<?php

final class DiffusionRepositoryEditController extends DiffusionController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $drequest = $this->diffusionRequest;
    $repository = $drequest->getRepository();

    $crumbs = $this->buildCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Edit')));

    $title = pht('Edit %s', $repository->getName());

    $header = id(new PHUIHeaderView())
      ->setHeader($title);
    if (!$repository->isTracked()) {
      $header->addTag(
        id(new PhabricatorTagView())
          ->setType(PhabricatorTagView::TYPE_STATE)
          ->setName(pht('Inactive'))
          ->setBackgroundColor(PhabricatorTagView::COLOR_BLACK));
    }

    $basic_actions = $this->buildBasicActions($repository);
    $basic_properties =
      $this->buildBasicProperties($repository, $basic_actions);

    $policy_actions = $this->buildPolicyActions($repository);
    $policy_properties =
      $this->buildPolicyProperties($repository, $policy_actions);

    $encoding_actions = $this->buildEncodingActions($repository);
    $encoding_properties =
      $this->buildEncodingProperties($repository, $encoding_actions);

    $xactions = id(new PhabricatorRepositoryTransactionQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($repository->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);
    foreach ($xactions as $xaction) {
      if ($xaction->getComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    $xaction_view = id(new PhabricatorApplicationTransactionView())
      ->setUser($user)
      ->setObjectPHID($repository->getPHID())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $obj_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($basic_properties)
      ->addPropertyList($policy_properties)
      ->addPropertyList($encoding_properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $obj_box,
        $xaction_view,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildBasicActions(PhabricatorRepository $repository) {
    $user = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($user);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit = id(new PhabricatorActionView())
      ->setIcon('edit')
      ->setName(pht('Edit Basic Information'))
      ->setHref($this->getRepositoryControllerURI($repository, 'edit/basic/'))
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);
    $view->addAction($edit);

    $activate = id(new PhabricatorActionView())
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/activate/'))
      ->setDisabled(!$can_edit)
      ->setWorkflow(true);

    if ($repository->isTracked()) {
      $activate
        ->setIcon('disable')
        ->setName(pht('Deactivate Repository'));
    } else {
      $activate
        ->setIcon('enable')
        ->setName(pht('Activate Repository'));
    }

    $view->addAction($activate);

    return $view;
  }

  private function buildBasicProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $user = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($user)
      ->setActionList($actions);

    $view->addProperty(pht('Name'), $repository->getName());
    $view->addProperty(pht('ID'), $repository->getID());
    $view->addProperty(pht('PHID'), $repository->getPHID());

    $type = PhabricatorRepositoryType::getNameForRepositoryType(
      $repository->getVersionControlSystem());

    $view->addProperty(pht('Type'), $type);
    $view->addProperty(pht('Callsign'), $repository->getCallsign());

    $description = $repository->getDetail('description');
    $view->addSectionHeader(pht('Description'));
    if (!strlen($description)) {
      $description = phutil_tag('em', array(), pht('No description provided.'));
    } else {
      $description = PhabricatorMarkupEngine::renderOneObject(
        $repository,
        'description',
        $user);
    }
    $view->addTextContent($description);

    return $view;
  }

  private function buildEncodingActions(PhabricatorRepository $repository) {
    $user = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($user);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit = id(new PhabricatorActionView())
      ->setIcon('edit')
      ->setName(pht('Edit Text Encoding'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/encoding/'))
      ->setWorkflow(!$can_edit)
      ->setDisabled(!$can_edit);
    $view->addAction($edit);

    return $view;
  }

  private function buildEncodingProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $user = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($user)
      ->setActionList($actions)
      ->addSectionHeader(pht('Text Encoding'));

    $encoding = $repository->getDetail('encoding');
    if (!$encoding) {
      $encoding = phutil_tag('em', array(), pht('Use Default (UTF-8)'));
    }

    $view->addProperty(pht('Encoding'), $encoding);

    return $view;
  }

  private function buildPolicyActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit = id(new PhabricatorActionView())
      ->setIcon('edit')
      ->setName(pht('Edit Policies'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/policy/'))
      ->setWorkflow(!$can_edit)
      ->setDisabled(!$can_edit);
    $view->addAction($edit);

    return $view;
  }

  private function buildPolicyProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions)
      ->addSectionHeader(pht('Policies'));

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $repository);

    $view->addProperty(
      pht('Visible To'),
      $descriptions[PhabricatorPolicyCapability::CAN_VIEW]);

    $view->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);


    return $view;
  }

}
