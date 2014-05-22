<?php

final class PhabricatorDashboardMovePanelController
  extends PhabricatorDashboardController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $column_id = $request->getStr('columnID');
    $panel_phid = $request->getStr('objectPHID');
    $after_phid = $request->getStr('afterPHID');
    $before_phid = $request->getStr('beforePHID');

    $dashboard = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needPanels(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$dashboard) {
      return new Aphront404Response();
    }
    $panels = mpull($dashboard->getPanels(), null, 'getPHID');
    $panel = idx($panels, $panel_phid);
    if (!$panel) {
      return new Aphront404Response();
    }

    $layout_config = $dashboard->getLayoutConfigObject();
    $layout_config->removePanel($panel_phid);
    $panel_location_grid = $layout_config->getPanelLocations();

    $panel_columns = idx($panel_location_grid, $column_id, array());
    if ($panel_columns) {
      $insert_at = 0;
      $new_panel_columns = $panel_columns;
      foreach ($panel_columns as $index => $curr_panel_phid) {
        if ($curr_panel_phid === $before_phid) {
          $insert_at = max($index - 1, 0);
          break;
        }
        if ($curr_panel_phid === $after_phid) {
          $insert_at = $index;
          break;
        }
      }
      array_splice(
        $new_panel_columns,
        $insert_at,
        0,
        array($panel_phid));
    } else {
      $new_panel_columns = array(0 => $panel_phid);
    }
    $panel_location_grid[$column_id] = $new_panel_columns;
    $layout_config->setPanelLocations($panel_location_grid);
    $dashboard->setLayoutConfigFromObject($layout_config);
    $dashboard->save();

    return id(new AphrontAjaxResponse())->setContent('');
 }

}
