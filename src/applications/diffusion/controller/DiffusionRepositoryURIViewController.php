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

    // For display, access the URI by loading it through the repository. This
    // may adjust builtin URIs for repository configuration, so we may end up
    // with a different view of builtin URIs than we'd see if we loaded them
    // directly from the database. See T12884.

    $repository_uris = $repository->getURIs();
    $repository_uris = mpull($repository_uris, null, 'getID');
    $uri = idx($repository_uris, $uri->getID());
    if (!$uri) {
      return new Aphront404Response();
    }

    $title = array(
      pht('URI'),
      $repository->getDisplayName(),
    );

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);
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
    $repository = $uri->getRepository();
    $id = $uri->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $uri,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($uri);

    $edit_uri = $uri->getEditURI();

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit URI'))
        ->setHref($edit_uri)
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    $credential_uri = $repository->getPathURI("uri/credential/{$id}/edit/");
    $remove_uri = $repository->getPathURI("uri/credential/{$id}/remove/");
    $has_credential = (bool)$uri->getCredentialPHID();

    if ($uri->isBuiltin()) {
      $can_credential = false;
    } else if (!$uri->newCommandEngine()->isCredentialSupported()) {
      $can_credential = false;
    } else {
      $can_credential = true;
    }

    $can_update = ($can_edit && $can_credential);
    $can_remove = ($can_edit && $has_credential);

    if ($has_credential) {
      $credential_name = pht('Update Credential');
    } else {
      $credential_name = pht('Set Credential');
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-key')
        ->setName($credential_name)
        ->setHref($credential_uri)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-times')
        ->setName(pht('Remove Credential'))
        ->setHref($remove_uri)
        ->setWorkflow(true)
        ->setDisabled(!$can_remove));

    if ($uri->getIsDisabled()) {
      $disable_name = pht('Enable URI');
      $disable_icon = 'fa-check';
    } else {
      $disable_name = pht('Disable URI');
      $disable_icon = 'fa-ban';
    }

    $can_disable = ($can_edit && !$uri->isBuiltin());

    $disable_uri = $repository->getPathURI("uri/disable/{$id}/");

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon($disable_icon)
        ->setName($disable_name)
        ->setHref($disable_uri)
        ->setWorkflow(true)
        ->setDisabled(!$can_disable));

    return $curtain;
  }

  private function buildPropertySection(PhabricatorRepositoryURI $uri) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $properties->addProperty(pht('URI'), $uri->getDisplayURI());

    $credential_phid = $uri->getCredentialPHID();
    $command_engine = $uri->newCommandEngine();
    $is_optional = $command_engine->isCredentialOptional();
    $is_supported = $command_engine->isCredentialSupported();
    $is_builtin = $uri->isBuiltin();

    if ($is_builtin) {
      $credential_icon = 'fa-circle-o';
      $credential_color = 'grey';
      $credential_label = pht('Builtin');
      $credential_note = pht('Builtin URIs do not use credentials.');
    } else if (!$is_supported) {
      $credential_icon = 'fa-circle-o';
      $credential_color = 'grey';
      $credential_label = pht('Not Supported');
      $credential_note = pht('This protocol does not support authentication.');
    } else if (!$credential_phid) {
      if ($is_optional) {
        $credential_icon = 'fa-circle-o';
        $credential_color = 'green';
        $credential_label = pht('No Credential');
        $credential_note = pht('Configured for anonymous access.');
      } else {
        $credential_icon = 'fa-times';
        $credential_color = 'red';
        $credential_label = pht('Required');
        $credential_note = pht('Credential required but not configured.');
      }
    } else {
      // Don't raise a policy exception if we can't see the credential.
      $credentials = id(new PassphraseCredentialQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($credential_phid))
        ->execute();
      $credential = head($credentials);

      if (!$credential) {
        $handles = $viewer->loadHandles(array($credential_phid));
        $handle = $handles[$credential_phid];
        if ($handle->getPolicyFiltered()) {
          $credential_icon = 'fa-lock';
          $credential_color = 'grey';
          $credential_label = pht('Restricted');
          $credential_note = pht(
            'You do not have permission to view the configured '.
            'credential.');
        } else {
          $credential_icon = 'fa-times';
          $credential_color = 'red';
          $credential_label = pht('Invalid');
          $credential_note = pht('Configured credential is invalid.');
        }
      } else {
        $provides = $credential->getProvidesType();
        $needs = $command_engine->getPassphraseProvidesCredentialType();
        if ($provides != $needs) {
          $credential_icon = 'fa-times';
          $credential_color = 'red';
          $credential_label = pht('Wrong Type');
        } else {
          $credential_icon = 'fa-check';
          $credential_color = 'green';
          $credential_label = $command_engine->getPassphraseCredentialLabel();
        }
        $credential_note = $viewer->renderHandle($credential_phid);
      }
    }

    $credential_item = id(new PHUIStatusItemView())
      ->setIcon($credential_icon, $credential_color)
      ->setTarget(phutil_tag('strong', array(), $credential_label))
      ->setNote($credential_note);

    $credential_view = id(new PHUIStatusListView())
      ->addItem($credential_item);

    $properties->addProperty(pht('Credential'), $credential_view);


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
