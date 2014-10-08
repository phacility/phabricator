<?php

final class HeraldRuleEditHistoryController extends HeraldController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();

    $edit_query = new HeraldEditLogQuery();
    if ($this->id) {
      $edit_query->withRuleIDs(array($this->id));
    }

    $pager = new AphrontPagerView();
    $pager->setURI($request->getRequestURI(), 'offset');
    $pager->setOffset($request->getStr('offset'));

    $edits = $edit_query->executeWithOffsetPager($pager);

    $need_phids = mpull($edits, 'getEditorPHID');
    $handles = $this->loadViewerHandles($need_phids);

    $list_view = id(new HeraldRuleEditHistoryView())
      ->setEdits($edits)
      ->setHandles($handles)
      ->setUser($this->getRequest()->getUser());

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Edit History'));
    $panel->appendChild($list_view);
    $panel->setNoBackground();

    $crumbs = $this
      ->buildApplicationCrumbs($can_create = false)
      ->addTextCrumb(
        pht('Edit History'),
        $this->getApplicationURI('herald/history'));

    $nav = $this->buildSideNavView();
    $nav->selectFilter('history');
    $nav->appendChild($panel);
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Rule Edit History'),
      ));
  }

}
