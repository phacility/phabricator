<?php

final class AphrontFileHTTPParameterType
  extends AphrontHTTPParameterType {

  private function getFileKey($key) {
    return $key.'_raw';
  }

  private function getDefaultKey($key) {
    return $key.'_default';
  }

  protected function getParameterExists(AphrontRequest $request, $key) {
    $file_key = $this->getFileKey($key);
    $default_key = $this->getDefaultKey($key);

    return $request->getExists($key) ||
           $request->getFileExists($file_key) ||
           $request->getExists($default_key);
  }

  protected function getParameterValue(AphrontRequest $request, $key) {
    $value = $request->getStrList($key);
    if ($value) {
      return head($value);
    }

    // NOTE: At least for now, we'll attempt to read a direct upload if we
    // miss on a PHID. Currently, PHUIFormFileControl does a client-side
    // upload on workflow forms (which is good) but doesn't have a hook for
    // non-workflow forms (which isn't as good). Giving it a hook is desirable,
    // but complicated. Even if we do hook it, it may be reasonable to keep
    // this code around as a fallback if the client-side JS goes awry.

    $file_key = $this->getFileKey($key);
    $default_key = $this->getDefaultKey($key);
    if (!$request->getFileExists($file_key)) {
      return $request->getStr($default_key);
    }

    $viewer = $this->getViewer();
    $file = PhabricatorFile::newFromPHPUpload(
      idx($_FILES, $file_key),
      array(
        'authorPHID' => $viewer->getPHID(),
        'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
      ));
    return $file->getPHID();
  }

  protected function getParameterTypeName() {
    return 'file';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('A file PHID.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'v=PHID-FILE-wxyz',
    );
  }

}
