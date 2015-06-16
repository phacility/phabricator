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
    $object_uri = nonempty($handle->getURI(), '/');

    $explanation = PhabricatorPolicy::getPolicyExplanation(
      $viewer,
      $policy->getPHID());

    $auto_info = (array)$object->describeAutomaticCapability($capability);

    $auto_info = array_merge(
      array($explanation),
      $auto_info);
    $auto_info = array_filter($auto_info);

    foreach ($auto_info as $key => $info) {
      $auto_info[$key] = phutil_tag('li', array(), $info);
    }
    if ($auto_info) {
      $auto_info = phutil_tag('ul', array(), $auto_info);
    }

    $capability_name = $capability;
    $capobj = PhabricatorPolicyCapability::getCapabilityByKey($capability);
    if ($capobj) {
      $capability_name = $capobj->getCapabilityName();
    }

    $space_info = null;
    if ($object instanceof PhabricatorSpacesInterface) {
      if (PhabricatorSpacesNamespaceQuery::getViewerSpacesExist($viewer)) {
        $space_phid = PhabricatorSpacesNamespaceQuery::getObjectSpacePHID(
          $object);

        $handles = $viewer->loadHandles(array($space_phid));

        $space_info = array(
          pht(
            'This object is in %s, and can only be seen by users with '.
            'access to that space.',
            $handles[$space_phid]->renderLink()),
          phutil_tag('br'),
          phutil_tag('br'),
        );
      }
    }

    $content = array(
      $space_info,
      pht('Users with the "%s" capability:', $capability_name),
      $auto_info,
    );

    $object_name = pht(
      '%s %s',
      $handle->getTypeName(),
      $handle->getObjectName());

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setClass('aphront-access-dialog')
      ->setTitle(pht('Policy Details: %s', $object_name))
      ->appendChild($content)
      ->addCancelButton($object_uri, pht('Done'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
