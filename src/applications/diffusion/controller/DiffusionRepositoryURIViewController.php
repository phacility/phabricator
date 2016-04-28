<?php

final class DiffusionRepositoryURIViewController
  extends DiffusionController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $id = $request->getURIData('id');

    $uri = id(new PhabricatorRepositoryURIQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->withRepositories(array($repository))
      ->executeOne();
    if (!$uri) {
      return new Aphront404Response();
    }

    $title = array(
      pht('URI'),
      $repository->getDisplayName(),
    );

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      $repository->getDisplayName(),
      $repository->getURI());
    $crumbs->addTextCrumb(
      pht('Manage'),
      $repository->getPathURI('manage/'));

    $panel_label = id(new DiffusionRepositoryURIsManagementPanel())
      ->getManagementPanelLabel();
    $panel_uri = $repository->getPathURI('manage/uris/');
    $crumbs->addTextCrumb($panel_label, $panel_uri);

    $crumbs->addTextCrumb(pht('URI %d', $uri->getID()));

    $header_text = pht(
      '%s: URI %d',
      $repository->getDisplayName(),
      $uri->getID());

    $header = id(new PHUIHeaderView())
      ->setHeader($header_text)
      ->setHeaderIcon('fa-pencil');
    if ($uri->getIsDisabled()) {
      $header->setStatus('fa-ban', 'dark', pht('Disabled'));
    } else {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    }

    $curtain = $this->buildCurtain($uri);
    $details = $this->buildPropertySection($uri);

    $timeline = $this->buildTransactionTimeline(
      $uri,
      new PhabricatorRepositoryURITransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setMainColumn(
        array(
          $details,
          $timeline,
        ))
      ->setCurtain($curtain);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildCurtain(PhabricatorRepositoryURI $uri) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $uri,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = $uri->getEditURI();

    $curtain = $this->newCurtainView($uri);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit URI'))
        ->setHref($edit_uri)
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    return $curtain;
  }

  private function buildPropertySection(PhabricatorRepositoryURI $uri) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $properties->addProperty(pht('URI'), $uri->getDisplayURI());
    $properties->addProperty(pht('Credential'), 'TODO');


    $io_type = $uri->getEffectiveIOType();
    $io_map = PhabricatorRepositoryURI::getIOTypeMap();
    $io_spec = idx($io_map, $io_type, array());

    $io_icon = idx($io_spec, 'icon');
    $io_color = idx($io_spec, 'color');
    $io_label = idx($io_spec, 'label', $io_type);
    $io_note = idx($io_spec, 'note');

    $io_item = id(new PHUIStatusItemView())
      ->setIcon($io_icon, $io_color)
      ->setTarget(phutil_tag('strong', array(), $io_label))
      ->setNote($io_note);

    $io_view = id(new PHUIStatusListView())
      ->addItem($io_item);

    $properties->addProperty(pht('I/O'), $io_view);


    $display_type = $uri->getEffectiveDisplayType();
    $display_map = PhabricatorRepositoryURI::getDisplayTypeMap();
    $display_spec = idx($display_map, $display_type, array());

    $display_icon = idx($display_spec, 'icon');
    $display_color = idx($display_spec, 'color');
    $display_label = idx($display_spec, 'label', $display_type);
    $display_note = idx($display_spec, 'note');

    $display_item = id(new PHUIStatusItemView())
      ->setIcon($display_icon, $display_color)
      ->setTarget(phutil_tag('strong', array(), $display_label))
      ->setNote($display_note);

    $display_view = id(new PHUIStatusListView())
      ->addItem($display_item);

    $properties->addProperty(pht('Display'), $display_view);


    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($properties);
  }

}
