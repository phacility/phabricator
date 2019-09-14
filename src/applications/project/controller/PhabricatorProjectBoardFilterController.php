<?php

final class PhabricatorProjectBoardFilterController
  extends PhabricatorProjectBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $project = $this->getProject();
    $state = $this->getViewState();
    $board_uri = $state->newWorkboardURI();

    $search_engine = $state->getSearchEngine();

    $is_submit = $request->isFormPost();

    if ($is_submit) {
      $saved_query = $search_engine->buildSavedQueryFromRequest($request);
      $search_engine->saveQuery($saved_query);
    } else {
      $saved_query = $state->getSavedQuery();
      if (!$saved_query) {
        return new Aphront404Response();
      }
    }

    $filter_form = id(new AphrontFormView())
      ->setUser($viewer);

    $search_engine->buildSearchForm($filter_form, $saved_query);

    $errors = $search_engine->getErrors();

    if ($is_submit && !$errors) {
      $query_key = $saved_query->getQueryKey();

      $state->setQueryKey($query_key);
      $board_uri = $state->newWorkboardURI();

      return id(new AphrontRedirectResponse())->setURI($board_uri);
    }

    return $this->newWorkboardDialog()
      ->setWidth(AphrontDialogView::WIDTH_FULL)
      ->setTitle(pht('Advanced Filter'))
      ->appendChild($filter_form->buildLayoutView())
      ->setErrors($errors)
      ->addSubmitButton(pht('Apply Filter'))
      ->addCancelButton($board_uri);
  }
}
