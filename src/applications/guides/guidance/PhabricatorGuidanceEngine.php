<?php

final class PhabricatorGuidanceEngine
  extends Phobject {

  private $viewer;
  private $guidanceContext;

  public function setGuidanceContext(
    PhabricatorGuidanceContext $guidance_context) {
    $this->guidanceContext = $guidance_context;
    return $this;
  }

  public function getGuidanceContext() {
    return $this->guidanceContext;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function newInfoView() {
    $extensions = PhabricatorGuidanceEngineExtension::getAllExtensions();
    $context = $this->getGuidanceContext();

    $keep = array();
    foreach ($extensions as $key => $extension) {
      if (!$extension->canGenerateGuidance($context)) {
        continue;
      }
      $keep[$key] = id(clone $extension);
    }

    $guidance_map = array();
    foreach ($keep as $extension) {
      $guidance_list = $extension->generateGuidance($context);
      foreach ($guidance_list as $guidance) {
        $key = $guidance->getKey();

        if (isset($guidance_map[$key])) {
          throw new Exception(
            pht(
              'Two guidance extensions generated guidance with the same '.
              'key ("%s"). Each piece of guidance must have a unique key.',
              $key));
        }

        $guidance_map[$key] = $guidance;
      }
    }

    foreach ($keep as $extension) {
      $guidance_map = $extension->didGenerateGuidance($context, $guidance_map);
    }

    if (!$guidance_map) {
      return null;
    }

    $guidance_map = msortv($guidance_map, 'getSortVector');

    $severity = PhabricatorGuidanceMessage::SEVERITY_NOTICE;
    $strength = null;
    foreach ($guidance_map as $guidance) {
      if ($strength !== null) {
        if ($guidance->getSeverityStrength() <= $strength) {
          continue;
        }
      }

      $strength = $guidance->getSeverityStrength();
      $severity = $guidance->getSeverity();
    }

    $severity_map = array(
      PhabricatorGuidanceMessage::SEVERITY_NOTICE
        => PHUIInfoView::SEVERITY_NOTICE,
      PhabricatorGuidanceMessage::SEVERITY_WARNING
        => PHUIInfoView::SEVERITY_WARNING,
    );

    $messages = mpull($guidance_map, 'getMessage', 'getKey');

    return id(new PHUIInfoView())
      ->setViewer($this->getViewer())
      ->setSeverity(idx($severity_map, $severity, $severity))
      ->setErrors($messages);
  }

}
