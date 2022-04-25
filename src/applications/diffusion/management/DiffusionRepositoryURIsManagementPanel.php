<?php

final class DiffusionRepositoryURIsManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'uris';

  public function getManagementPanelLabel() {
    return pht('URIs');
  }

  public function getManagementPanelIcon() {
    return 'fa-globe';
  }

  public function getManagementPanelOrder() {
    return 400;
  }

  public function buildManagementPanelCurtain() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();
    $action_list = $this->newActionList();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $doc_href = PhabricatorEnv::getDoclink('Diffusion User Guide: URIs');
    $add_href = $repository->getPathURI('uri/edit/');

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-plus')
        ->setHref($add_href)
        ->setDisabled(!$can_edit)
        ->setName(pht('Add New URI')));

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-book')
        ->setHref($doc_href)
        ->setName(pht('URI Documentation')));

    return $this->newCurtainView()
      ->setActionList($action_list);
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();
    $uris = $repository->getURIs();

    Javelin::initBehavior('phabricator-tooltips');
    $rows = array();
    foreach ($uris as $uri) {

      $uri_name = $uri->getDisplayURI();
      $uri_name = phutil_tag(
        'a',
        array(
          'href' => $uri->getViewURI(),
        ),
        $uri_name);

      if ($uri->getIsDisabled()) {
        $status_icon = 'fa-times grey';
      } else {
        $status_icon = 'fa-check green';
      }

      $uri_status = id(new PHUIIconView())->setIcon($status_icon);

      $io_type = $uri->getEffectiveIOType();
      $io_map = PhabricatorRepositoryURI::getIOTypeMap();
      $io_spec = idx($io_map, $io_type, array());

      $io_icon = idx($io_spec, 'icon');
      $io_color = idx($io_spec, 'color');
      $io_label = idx($io_spec, 'label', $io_type);

      $uri_io = array(
        id(new PHUIIconView())->setIcon("{$io_icon} {$io_color}"),
        ' ',
        $io_label,
      );

      $display_type = $uri->getEffectiveDisplayType();
      $display_map = PhabricatorRepositoryURI::getDisplayTypeMap();
      $display_spec = idx($display_map, $display_type, array());

      $display_icon = idx($display_spec, 'icon');
      $display_color = idx($display_spec, 'color');
      $display_label = idx($display_spec, 'label', $display_type);

      $uri_display = array(
        id(new PHUIIconView())->setIcon("{$display_icon} {$display_color}"),
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

    $is_new = $repository->isNewlyInitialized();

    $messages = array();
    if ($repository->isHosted()) {
      if ($is_new) {
        $host_message = pht('This repository will be hosted.');
      } else {
        $host_message = pht('This repository is observed.');
      }

      $messages[] = $host_message;
    } else {
      if ($is_new) {
        $observe_message = pht(
          'This repository will be observed.');
      } else {
        $observe_message = pht(
          'This remote repository is being observed.');
      }

      $messages[] = $observe_message;
    }

    $info_view = id(new PHUIInfoView())
      ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
      ->setErrors($messages);

    $box = $this->newBox(pht('Repository URIs'), $table);

    return array($info_view, $box);
  }

}
