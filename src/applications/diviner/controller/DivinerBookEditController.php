<?php

final class DivinerBookEditController extends DivinerController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $book_name = $request->getURIData('book');

    $book = id(new DivinerBookQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->needProjectPHIDs(true)
      ->withNames(array($book_name))
      ->executeOne();

    if (!$book) {
      return new Aphront404Response();
    }

    $view_uri = '/book/'.$book->getName().'/';

    if ($request->isFormPost()) {
      $v_projects = $request->getArr('projectPHIDs');
      $v_view     = $request->getStr('viewPolicy');
      $v_edit     = $request->getStr('editPolicy');

      $xactions = array();
      $xactions[] = id(new DivinerLiveBookTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue(
          'edge:type',
          PhabricatorProjectObjectHasProjectEdgeType::EDGECONST)
        ->setNewValue(
          array(
            '=' => array_fuse($v_projects),
          ));
      $xactions[] = id(new DivinerLiveBookTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
        ->setNewValue($v_view);
      $xactions[] = id(new DivinerLiveBookTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
        ->setNewValue($v_edit);

      id(new DivinerLiveBookEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->applyTransactions($book, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Basics'));

    $title = pht('Edit %s', $book->getTitle());

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($book)
      ->execute();
    $view_capability = PhabricatorPolicyCapability::CAN_VIEW;
    $edit_capability = PhabricatorPolicyCapability::CAN_EDIT;

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorProjectDatasource())
          ->setName('projectPHIDs')
          ->setLabel(pht('Projects'))
          ->setValue($book->getProjectPHIDs()))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new DiffusionRepositoryDatasource())
          ->setName('repositoryPHIDs')
          ->setLabel(pht('Repository'))
          ->setDisableBehavior(true)
          ->setLimit(1)
          ->setValue($book->getRepositoryPHID()
            ? array($book->getRepositoryPHID())
            : null))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($book)
          ->setCapability($view_capability)
          ->setPolicies($policies)
          ->setCaption($book->describeAutomaticCapability($view_capability)))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($book)
          ->setCapability($edit_capability)
          ->setPolicies($policies)
          ->setCaption($book->describeAutomaticCapability($edit_capability)))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save'))
          ->addCancelButton($view_uri));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setForm($form);

    $timeline = $this->buildTransactionTimeline(
      $book,
      new DivinerLiveBookTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

}
