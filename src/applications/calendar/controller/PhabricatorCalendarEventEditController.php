<?php

final class PhabricatorCalendarEventEditController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $engine = id(new PhabricatorCalendarEventEditEngine())
      ->setController($this);

    $id = $request->getURIData('id');
    if ($id) {
      $event = id(new PhabricatorCalendarEventQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->executeOne();
      $response = $this->newImportedEventResponse($event);
      if ($response) {
        return $response;
      }

      $cancel_uri = $event->getURI();

      $page = $request->getURIData('pageKey');
      if ($page == 'recurring') {
        if ($event->isChildEvent()) {
          return $this->newDialog()
            ->setTitle(pht('Series Event'))
            ->appendParagraph(
              pht(
                'This event is an instance in an event series. To change '.
                'the behavior for the series, edit the parent event.'))
            ->addCancelButton($cancel_uri);
        }
      } else if ($event->getIsRecurring()) {

        // If the user submits a comment or makes an edit via comment actions,
        // always target only the current event. It doesn't make sense to add
        // comments to every instance of an event, and the other actions don't
        // make much sense to apply to all instances either.
        if ($engine->isCommentAction()) {
          $mode = PhabricatorCalendarEventEditEngine::MODE_THIS;
        } else {
          $mode = $request->getStr('mode');
        }

        if (!$mode) {
          $form = id(new AphrontFormView())
            ->setViewer($viewer)
            ->appendControl(
              id(new AphrontFormSelectControl())
                ->setLabel(pht('Edit Events'))
                ->setName('mode')
                ->setOptions(
                  array(
                    PhabricatorCalendarEventEditEngine::MODE_THIS
                      => pht('Edit Only This Event'),
                    PhabricatorCalendarEventEditEngine::MODE_FUTURE
                      => pht('Edit All Future Events'),
                  )));

          return $this->newDialog()
            ->setTitle(pht('Edit Event'))
            ->appendParagraph(
              pht(
                'This event is part of a series. Which events do you '.
                'want to edit?'))
            ->appendForm($form)
            ->addSubmitButton(pht('Continue'))
            ->addCancelButton($cancel_uri)
            ->setDisableWorkflowOnSubmit(true);
        }

        $engine
          ->addContextParameter('mode', $mode)
          ->setSeriesEditMode($mode);
      }
    }

    return $engine->buildResponse();
  }

}
