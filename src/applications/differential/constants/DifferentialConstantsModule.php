<?php

final class DifferentialConstantsModule
  extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'constants.differential';
  }

  public function getModuleName() {
    return pht('Constants: Differential');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    return array(
      $this->renderRevisionStatuses($viewer),
      $this->renderUnitStatuses($viewer),
      $this->renderLintStatuses($viewer),
    );
  }

  private function renderRevisionStatuses(PhabricatorUser $viewer) {
    $statuses = DifferentialRevisionStatus::getAll();

    $rows = array();
    foreach ($statuses as $status) {
      $icon = id(new PHUIIconView())
        ->setIcon(
          $status->getIcon(),
          $status->getIconColor());

      $timeline_icon = $status->getTimelineIcon();
      if ($timeline_icon !== null) {
        $timeline_view = id(new PHUIIconView())
          ->setIcon(
            $status->getTimelineIcon(),
            $status->getTimelineColor());
      } else {
        $timeline_view = null;
      }

      if ($status->isClosedStatus()) {
        $is_open = pht('Closed');
      } else {
        $is_open = pht('Open');
      }

      $tag_color = $status->getTagColor();
      if ($tag_color !== null) {
        $tag_view = id(new PHUIIconView())
          ->seticon('fa-tag', $tag_color);
      } else {
        $tag_view = null;
      }

      $ansi_color = $status->getAnsiColor();
      if ($ansi_color !== null) {
        $web_color = PHUIColor::getWebColorFromANSIColor($ansi_color);
        $ansi_view = id(new PHUIIconView())
          ->setIcon('fa-stop', $web_color);
      } else {
        $ansi_view = null;
      }


      $rows[] = array(
        $status->getKey(),
        $status->getLegacyKey(),
        $icon,
        $timeline_view,
        $tag_view,
        $ansi_view,
        $is_open,
        $status->getDisplayName(),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Value'),
          pht('Legacy Value'),
          pht('Icon'),
          pht('Timeline Icon'),
          pht('Tag Color'),
          pht('ANSI Color'),
          pht('Open/Closed'),
          pht('Name'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          null,
          null,
          null,
          null,
          null,
          'wide pri',
        ));

    $view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Differential Revision Statuses'))
      ->setTable($table);

    return $view;
  }

  private function renderUnitStatuses(PhabricatorUser $viewer) {
    $statuses = DifferentialUnitStatus::getStatusMap();

    $rows = array();
    foreach ($statuses as $status) {
      $rows[] = array(
        $status->getValue(),
        id(new PHUIIconView())
          ->setIcon($status->getIconIcon(), $status->getIconColor()),
        $status->getName(),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Value'),
          pht('Icon'),
          pht('Name'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'wide pri',
        ));

    $view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Differential Unit Statuses'))
      ->setTable($table);

    return $view;
  }

  private function renderLintStatuses(PhabricatorUser $viewer) {
    $statuses = DifferentialLintStatus::getStatusMap();

    $rows = array();
    foreach ($statuses as $status) {
      $rows[] = array(
        $status->getValue(),
        id(new PHUIIconView())
          ->setIcon($status->getIconIcon(), $status->getIconColor()),
        $status->getName(),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Value'),
          pht('Icon'),
          pht('Name'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'wide pri',
        ));

    $view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Differential Lint Statuses'))
      ->setTable($table);

    return $view;
  }


}
