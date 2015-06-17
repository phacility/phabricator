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

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setClass('aphront-access-dialog');

    $this->appendSpaceInformation($dialog, $object, $policy, $capability);

    $intro = pht(
      'Users with the "%s" capability for this object:',
      $capability_name);

    $object_name = pht(
      '%s %s',
      $handle->getTypeName(),
      $handle->getObjectName());

    return $dialog
      ->setTitle(pht('Policy Details: %s', $object_name))
      ->appendParagraph($intro)
      ->appendChild($auto_info)
      ->addCancelButton($object_uri, pht('Done'));
  }

  private function appendSpaceInformation(
    AphrontDialogView $dialog,
    PhabricatorPolicyInterface $object,
    PhabricatorPolicy $policy,
    $capability) {
    $viewer = $this->getViewer();

    if (!($object instanceof PhabricatorSpacesInterface)) {
      return;
    }

    if (!PhabricatorSpacesNamespaceQuery::getSpacesExist($viewer)) {
      return;
    }

    // NOTE: We're intentionally letting users through here, even if they only
    // have access to one space. The intent is to help users in "space jail"
    // understand who objects they create are visible to:

    $space_phid = PhabricatorSpacesNamespaceQuery::getObjectSpacePHID(
      $object);

    $handles = $viewer->loadHandles(array($space_phid));
    $doc_href = PhabricatorEnv::getDoclink('Spaces User Guide');

    $dialog->appendParagraph(
      array(
        pht(
          'This object is in %s, and can only be seen or edited by users with '.
          'access to view objects in the space.',
          $handles[$space_phid]->renderLink()),
        ' ',
        phutil_tag(
          'strong',
          array(),
          phutil_tag(
            'a',
            array(
              'href' => $doc_href,
              'target' => '_blank',
            ),
            pht('Learn More'))),
      ));

    $spaces = PhabricatorSpacesNamespaceQuery::getViewerSpaces($viewer);
    $space = idx($spaces, $space_phid);
    if (!$space) {
      return;
    }

    $space_policies = PhabricatorPolicyQuery::loadPolicies($viewer, $space);
    $space_policy = idx($space_policies, PhabricatorPolicyCapability::CAN_VIEW);
    if (!$space_policy) {
      return;
    }

    $space_explanation = PhabricatorPolicy::getPolicyExplanation(
      $viewer,
      $space_policy->getPHID());
    $items = array();
    $items[] = $space_explanation;

    foreach ($items as $key => $item) {
      $items[$key] = phutil_tag('li', array(), $item);
    }

    $dialog->appendParagraph(pht('Users who can see objects in this space:'));
    $dialog->appendChild(phutil_tag('ul', array(), $items));

    $view_capability = PhabricatorPolicyCapability::CAN_VIEW;
    if ($capability == $view_capability) {
      $stronger = $space_policy->isStrongerThan($policy);
      if ($stronger) {
        $dialog->appendParagraph(
          pht(
            'The space this object is in has a more restrictive view '.
            'policy ("%s") than the object does ("%s"), so the space\'s '.
            'view policy is shown as a hint instead of the object policy.',
            $space_policy->getShortName(),
            $policy->getShortName()));
      }
    }

    $dialog->appendParagraph(
      pht(
        'After a user passes space policy checks, they must still pass '.
        'object policy checks.'));
  }

}
