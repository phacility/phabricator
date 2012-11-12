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
    $panel->setHeader('Edit History');
    $panel->appendChild($list_view);

    $nav = $this->renderNav();
    $nav->selectFilter('history');
    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Rule Edit History',
      ));
  }

}
