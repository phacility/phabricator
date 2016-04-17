<?php

final class DiffusionRepositoryURIsManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'uris';

  public function getManagementPanelLabel() {
    return pht('Clone / Fetch / Mirror');
  }

  public function getManagementPanelOrder() {
    return 300;
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $repository->attachURIs(array());
    $uris = $repository->getURIs();

    Javelin::initBehavior('phabricator-tooltips');
    $rows = array();
    foreach ($uris as $uri) {

      $uri_name = $uri->getDisplayURI();

      if ($uri->getIsDisabled()) {
        $status_icon = 'fa-times grey';
      } else {
        $status_icon = 'fa-check green';
      }

      $uri_status = id(new PHUIIconView())->setIcon($status_icon);

      switch ($uri->getEffectiveIOType()) {
        case PhabricatorRepositoryURI::IO_OBSERVE:
          $io_icon = 'fa-download green';
          $io_label = pht('Observe');
          break;
        case PhabricatorRepositoryURI::IO_MIRROR:
          $io_icon = 'fa-upload green';
          $io_label = pht('Mirror');
          break;
        case PhabricatorRepositoryURI::IO_NONE:
          $io_icon = 'fa-times grey';
          $io_label = pht('No I/O');
          break;
        case PhabricatorRepositoryURI::IO_READ:
          $io_icon = 'fa-folder blue';
          $io_label = pht('Read Only');
          break;
        case PhabricatorRepositoryURI::IO_READWRITE:
          $io_icon = 'fa-folder-open blue';
          $io_label = pht('Read/Write');
          break;
      }

      $uri_io = array(
        id(new PHUIIconView())->setIcon($io_icon),
        ' ',
        $io_label,
      );

      switch ($uri->getEffectiveDisplayType()) {
        case PhabricatorRepositoryURI::DISPLAY_NEVER:
          $display_icon = 'fa-eye-slash grey';
          $display_label = pht('Hidden');
          break;
        case PhabricatorRepositoryURI::DISPLAY_ALWAYS:
          $display_icon = 'fa-eye green';
          $display_label = pht('Visible');
          break;
      }

      $uri_display = array(
        id(new PHUIIconView())->setIcon($display_icon),
        ' ',
        $display_label,
      );

      $rows[] = array(
        $uri_status,
        $uri_name,
        $uri_io,
        $uri_display,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('This repository has no URIs.'))
      ->setHeaders(
        array(
          null,
          pht('URI'),
          pht('I/O'),
          pht('Display'),
        ))
      ->setColumnClasses(
        array(
          null,
          'pri wide',
          null,
          null,
        ));

    $doc_href = PhabricatorEnv::getDoclink(
      'Diffusion User Guide: Repository URIs');

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Repository URIs'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setIcon('fa-book')
          ->setHref($doc_href)
          ->setTag('a')
          ->setText(pht('Documentation')));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }

}
