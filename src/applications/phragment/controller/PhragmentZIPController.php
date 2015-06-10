<?php

final class PhragmentZIPController extends PhragmentController {

  private $dblob;
  private $snapshot;

  private $snapshotCache;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->dblob = idx($data, 'dblob', '');
    $this->snapshot = idx($data, 'snapshot', null);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $parents = $this->loadParentFragments($this->dblob);
    if ($parents === null) {
      return new Aphront404Response();
    }
    $fragment = idx($parents, count($parents) - 1, null);

    if ($this->snapshot !== null) {
      $snapshot = id(new PhragmentSnapshotQuery())
        ->setViewer($viewer)
        ->withPrimaryFragmentPHIDs(array($fragment->getPHID()))
        ->withNames(array($this->snapshot))
        ->executeOne();
      if ($snapshot === null) {
        return new Aphront404Response();
      }

      $cache = id(new PhragmentSnapshotChildQuery())
        ->setViewer($viewer)
        ->needFragmentVersions(true)
        ->withSnapshotPHIDs(array($snapshot->getPHID()))
        ->execute();
      $this->snapshotCache = mpull(
        $cache,
        'getFragmentVersion',
        'getFragmentPHID');
    }

    $temp = new TempFile();

    $zip = null;
    try {
      $zip = new ZipArchive();
    } catch (Exception $e) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($viewer);

      $inst = pht(
        'This system does not have the ZIP PHP extension installed. This '.
        'is required to download ZIPs from Phragment.');

      $dialog->setTitle(pht('ZIP Extension Not Installed'));
      $dialog->appendParagraph($inst);

      $dialog->addCancelButton('/phragment/browse/'.$this->dblob);
      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    if (!$zip->open((string)$temp, ZipArchive::CREATE)) {
      throw new Exception(pht('Unable to create ZIP archive!'));
    }

    $mappings = $this->getFragmentMappings($fragment, $fragment->getPath());

    $phids = array();
    foreach ($mappings as $path => $file_phid) {
      $phids[] = $file_phid;
    }

    $files = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();
    $files = mpull($files, null, 'getPHID');
    foreach ($mappings as $path => $file_phid) {
      if (!isset($files[$file_phid])) {
        // The path is most likely pointing to a deleted fragment, which
        // hence no longer has a file associated with it.
        unset($mappings[$path]);
        continue;
      }
      $mappings[$path] = $files[$file_phid];
    }

    foreach ($mappings as $path => $file) {
      if ($file !== null) {
        $zip->addFromString($path, $file->loadFileData());
      }
    }
    $zip->close();

    $zip_name = $fragment->getName();
    if (substr($zip_name, -4) !== '.zip') {
      $zip_name .= '.zip';
    }

    $data = Filesystem::readFile((string)$temp);
    $file = PhabricatorFile::buildFromFileDataOrHash(
      $data,
      array(
        'name' => $zip_name,
        'ttl' => time() + 60 * 60 * 24,
      ));

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $file->attachToObject($fragment->getPHID());
    unset($unguarded);

    $return = $fragment->getURI();
    if ($request->getExists('return')) {
      $return = $request->getStr('return');
    }

    return id(new AphrontRedirectResponse())
      ->setIsExternal(true)
      ->setURI($file->getDownloadURI($return));
  }

  /**
   * Returns a list of mappings like array('some/path.txt' => 'file PHID');
   */
  private function getFragmentMappings(PhragmentFragment $current, $base_path) {
    $mappings = $current->getFragmentMappings(
      $this->getRequest()->getUser(),
      $base_path);

    $result = array();
    foreach ($mappings as $path => $fragment) {
      $version = $this->getVersion($fragment);
      if ($version !== null) {
        $result[$path] = $version->getFilePHID();
      }
    }
    return $result;
  }

  private function getVersion($fragment) {
    if ($this->snapshot === null) {
      return $fragment->getLatestVersion();
    } else {
      return idx($this->snapshotCache, $fragment->getPHID(), null);
    }
  }

}
