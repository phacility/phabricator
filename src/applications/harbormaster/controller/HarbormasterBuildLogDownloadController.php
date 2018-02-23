<?php

final class HarbormasterBuildLogDownloadController
  extends HarbormasterController {

  public function handleRequest(AphrontRequest $request) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $request->getURIData('id');

    $log = id(new HarbormasterBuildLogQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$log) {
      return new Aphront404Response();
    }

    $cancel_uri = $log->getURI();
    $file_phid = $log->getFilePHID();

    if (!$file_phid) {
      return $this->newDialog()
        ->setTitle(pht('Log Not Finalized'))
        ->appendParagraph(
          pht(
            'Logs must be fully written and processed before they can be '.
            'downloaded. This log is still being written or processed.'))
        ->addCancelButton($cancel_uri, pht('Wait Patiently'));
    }

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($file_phid))
      ->executeOne();
    if (!$file) {
      return $this->newDialog()
        ->setTitle(pht('Unable to Load File'))
        ->appendParagraph(
          pht(
            'Unable to load the file for this log. The file may have been '.
            'destroyed.'))
        ->addCancelButton($cancel_uri);
    }

    $size = $file->getByteSize();

    return $this->newDialog()
      ->setTitle(pht('Download Build Log'))
      ->appendParagraph(
        pht(
          'This log has a total size of %s. If you insist, you may '.
          'download it.',
          phutil_tag('strong', array(), phutil_format_bytes($size))))
      ->setDisableWorkflowOnSubmit(true)
      ->addSubmitButton(pht('Download Log'))
      ->setSubmitURI($file->getDownloadURI())
      ->addCancelButton($cancel_uri, pht('Done'));
  }

}
