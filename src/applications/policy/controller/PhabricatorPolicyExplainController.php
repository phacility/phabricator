<?php

final class PhabricatorPolicyExplainController
  extends PhabricatorPolicyController {

  private $phid;
  private $capability;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
    $this->capability = $data['capability'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $phid = $this->phid;
    $capability = $this->capability;

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }

    $policies = PhabricatorPolicyQuery::loadPolicies(
      $viewer,
      $object);

    $policy = idx($policies, $capability);
    if (!$policy) {
      return new Aphront404Response();
    }

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();
    $object_uri = $handle->getURI();

    $explanation = $policy->getExplanation($capability);
    $auto_info = (array)$object->describeAutomaticCapability($capability);

    foreach ($auto_info as $key => $info) {
      $auto_info[$key] = phutil_tag('li', array(), $info);
    }
    if ($auto_info) {
      $auto_info = phutil_tag('ul', array(), $auto_info);
    }

    $content = array(
      $explanation,
      $auto_info,
    );

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setClass('aphront-access-dialog')
      ->setTitle(pht('Policy Details'))
      ->appendChild($content)
      ->addCancelButton($object_uri, pht('Done'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
