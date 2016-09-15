<?php

final class PhabricatorCalendarEventExportController
  extends PhabricatorCalendarController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$event) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $file_name = $event->getMonogram().'.ics';

      $event_node = $event->newIntermediateEventNode($viewer);

      $document_node = id(new PhutilCalendarDocumentNode())
        ->appendChild($event_node);

      $root_node = id(new PhutilCalendarRootNode())
        ->appendChild($document_node);

      $ics_data = id(new PhutilICSWriter())
        ->writeICSDocument($root_node);

      return id(new AphrontFileResponse())
        ->setDownload($file_name)
        ->setMimeType('text/calendar')
        ->setContent($ics_data);
    }

    return $this->newDialog()
      ->setDisableWorkflowOnSubmit(true)
      ->setTitle(pht('Export as .ics'))
      ->appendParagraph(
        pht(
          'WARNING: This feature is a prototype and only supports a limited '.
          'set of features. Keep your expectations low!'))
      ->addSubmitButton(pht('Download .ics'))
      ->addCancelButton($event->getURI(), pht('Close'));

  }

}
