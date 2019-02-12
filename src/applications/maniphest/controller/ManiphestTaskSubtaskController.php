<?php

final class ManiphestTaskSubtaskController
  extends ManiphestController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $task = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$task) {
      return new Aphront404Response();
    }

    $cancel_uri = $task->getURI();

    $edit_engine = id(new ManiphestEditEngine())
      ->setViewer($viewer)
      ->setTargetObject($task);

    $subtype_map = $task->newEditEngineSubtypeMap();

    $subtype_options = $subtype_map->getCreateFormsForSubtype(
      $edit_engine,
      $task);

    if (!$subtype_options) {
      return $this->newDialog()
        ->setTitle(pht('No Forms'))
        ->appendParagraph(
          pht(
            'You do not have access to any forms which can be used to '.
            'create a subtask.'))
        ->addCancelButton($cancel_uri, pht('Close'));
    }

    $menu = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setBig(true)
      ->setFlush(true);

    foreach ($subtype_options as $form_key => $subtype_form) {
      $subtype_key = $subtype_form->getSubtype();
      $subtype = $subtype_map->getSubtype($subtype_key);

      $subtask_uri = id(new PhutilURI("/task/edit/form/{$form_key}/"))
        ->replaceQueryParam('parent', $id)
        ->replaceQueryParam('template', $id)
        ->replaceQueryParam('status', ManiphestTaskStatus::getDefaultStatus());
      $subtask_uri = $this->getApplicationURI($subtask_uri);

      $item = id(new PHUIObjectItemView())
        ->setHeader($subtype_form->getDisplayName())
        ->setHref($subtask_uri)
        ->setClickable(true)
        ->setImageIcon($subtype->newIconView())
        ->addAttribute($subtype->getName());

      $menu->addItem($item);
    }

    return $this->newDialog()
      ->setTitle(pht('Choose Subtype'))
      ->appendChild($menu)
      ->addCancelButton($cancel_uri);
  }

}
