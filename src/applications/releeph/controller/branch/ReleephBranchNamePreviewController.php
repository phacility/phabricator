<?php

final class ReleephBranchNamePreviewController
  extends ReleephController {

  public function processRequest() {
    $request = $this->getRequest();

    $is_symbolic = $request->getBool('isSymbolic');
    $template = $request->getStr('template');

    if (!$is_symbolic && !$template) {
      $template = ReleephBranchTemplate::getDefaultTemplate();
    }

    $repository_phid = $request->getInt('repositoryPHID');
    $fake_commit_handle =
      ReleephBranchTemplate::getFakeCommitHandleFor(
        $repository_phid,
        $request->getUser());

    list($name, $errors) = id(new ReleephBranchTemplate())
      ->setCommitHandle($fake_commit_handle)
      ->setReleephProjectName($request->getStr('projectName'))
      ->setSymbolic($is_symbolic)
      ->interpolate($template);

    $markup = '';

    if ($name) {
      $markup = phutil_tag(
        'div',
        array('class' => 'name'),
        $name);
    }

    if ($errors) {
      $markup .= phutil_tag(
        'div',
        array('class' => 'error'),
        head($errors));
    }

    return id(new AphrontAjaxResponse())
      ->setContent(array('markup' => $markup));
  }

}
