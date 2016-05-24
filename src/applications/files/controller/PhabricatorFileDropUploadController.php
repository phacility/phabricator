<?php

final class PhabricatorFileDropUploadController
  extends PhabricatorFileController {

  public function shouldAllowRestrictedParameter($parameter_name) {
    // Prevent false positives from file content when it is submitted via
    // drag-and-drop upload.
    return true;
  }

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    // NOTE: Throws if valid CSRF token is not present in the request.
    $request->validateCSRF();

    $name = $request->getStr('name');
    $file_phid = $request->getStr('phid');
    // If there's no explicit view policy, make it very restrictive by default.
    // This is the correct policy for files dropped onto objects during
    // creation, comment and edit flows.
    $view_policy = $request->getStr('viewPolicy');
    if (!$view_policy) {
      $view_policy = $viewer->getPHID();
    }

    $is_chunks = $request->getBool('querychunks');
    if ($is_chunks) {
      $params = array(
        'filePHID' => $file_phid,
      );

      $result = id(new ConduitCall('file.querychunks', $params))
        ->setUser($viewer)
        ->execute();

      return id(new AphrontAjaxResponse())->setContent($result);
    }

    $is_allocate = $request->getBool('allocate');
    if ($is_allocate) {
      $params = array(
        'name' => $name,
        'contentLength' => $request->getInt('length'),
        'viewPolicy' => $view_policy,
      );

      $result = id(new ConduitCall('file.allocate', $params))
        ->setUser($viewer)
        ->execute();

      $file_phid = $result['filePHID'];
      if ($file_phid) {
        $file = $this->loadFile($file_phid);
        $result += $file->getDragAndDropDictionary();
      }

      return id(new AphrontAjaxResponse())->setContent($result);
    }

    // Read the raw request data. We're either doing a chunk upload or a
    // vanilla upload, so we need it.
    $data = PhabricatorStartup::getRawInput();

    $is_chunk_upload = $request->getBool('uploadchunk');
    if ($is_chunk_upload) {
      $params = array(
        'filePHID' => $file_phid,
        'byteStart' => $request->getInt('byteStart'),
        'data' => $data,
      );

      $result = id(new ConduitCall('file.uploadchunk', $params))
        ->setUser($viewer)
        ->execute();

      $file = $this->loadFile($file_phid);
      if ($file->getIsPartial()) {
        $result = array();
      } else {
        $result = array(
          'complete' => true,
        ) + $file->getDragAndDropDictionary();
      }

      return id(new AphrontAjaxResponse())->setContent($result);
    }

    $file = PhabricatorFile::newFromXHRUpload(
      $data,
      array(
        'name' => $request->getStr('name'),
        'authorPHID' => $viewer->getPHID(),
        'viewPolicy' => $view_policy,
        'isExplicitUpload' => true,
      ));

    $result = $file->getDragAndDropDictionary();
    return id(new AphrontAjaxResponse())->setContent($result);
  }

  private function loadFile($file_phid) {
    $viewer = $this->getViewer();

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($file_phid))
      ->executeOne();
    if (!$file) {
      throw new Exception(pht('Failed to load file.'));
    }

    return $file;
  }

}
