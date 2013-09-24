<?php

final class DiffusionRepositoryEditController extends DiffusionController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $drequest = $this->diffusionRequest;
    $repository = $drequest->getRepository();

    $content = array();

    $crumbs = $this->buildCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Edit')));
    $content[] = $crumbs;

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

    $content[] = $header;

    $content[] = $this->buildBasicActions($repository);
    $content[] = $this->buildBasicProperties($repository);

    $content[] = id(new PHUIHeaderView())
      ->setHeader(pht('Policies'));

    $content[] = $this->buildPolicyActions($repository);
    $content[] = $this->buildPolicyProperties($repository);

    $content[] = id(new PHUIHeaderView())
      ->setHeader(pht('Text Encoding'));

    $content[] = $this->buildEncodingActions($repository);
    $content[] = $this->buildEncodingProperties($repository);

    $content[] = id(new PHUIHeaderView())
      ->setHeader(pht('Edit History'));

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

    $content[] = $xaction_view;


    return $this->buildApplicationPage(
      $content,
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

  private function buildBasicProperties(PhabricatorRepository $repository) {
    $user = $this->getRequest()->getUser();

    $view = id(new PhabricatorPropertyListView())
      ->setUser($user);

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

  private function buildEncodingProperties(PhabricatorRepository $repository) {
    $user = $this->getRequest()->getUser();

    $view = id(new PhabricatorPropertyListView())
      ->setUser($user);

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

  private function buildPolicyProperties(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorPropertyListView())
      ->setUser($viewer);

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
