<?php

final class PhragmentUpdateController extends PhragmentController {

  private $dblob;

  public function willProcessRequest(array $data) {
    $this->dblob = idx($data, 'dblob', '');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $parents = $this->loadParentFragments($this->dblob);
    if ($parents === null) {
      return new Aphront404Response();
    }
    $fragment = idx($parents, count($parents) - 1, null);

    $error_view = null;

    if ($request->isFormPost()) {
      $errors = array();

      $v_fileid = $request->getInt('fileID');

      $file = id(new PhabricatorFile())->load($v_fileid);
      if ($file === null) {
        $errors[] = pht('The specified file doesn\'t exist.');
      }

      if (!count($errors)) {
        // If the file is a ZIP archive (has application/zip mimetype)
        // then we extract the zip and apply versions for each of the
        // individual fragments, creating and deleting files as needed.
        if ($file->getMimeType() === 'application/zip') {
          $fragment->updateFromZIP($viewer, $file);
        } else {
          $fragment->updateFromFile($viewer, $file);
        }

        return id(new AphrontRedirectResponse())
          ->setURI('/phragment/browse/'.$fragment->getPath());
      } else {
        $error_view = id(new PHUIInfoView())
          ->setErrors($errors)
          ->setTitle(pht('Errors while updating fragment'));
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('File ID'))
          ->setName('fileID'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Update Fragment'))
          ->addCancelButton(
            $this->getApplicationURI('browse/'.$fragment->getPath())));

    $crumbs = $this->buildApplicationCrumbsWithPath($parents);
    $crumbs->addTextCrumb(pht('Update Fragment'));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Update Fragment: %s', $fragment->getPath()))
      ->setValidationException(null)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $this->renderConfigurationWarningIfRequired(),
        $box,
      ),
      array(
        'title' => pht('Update Fragment'),
      ));
  }

}
