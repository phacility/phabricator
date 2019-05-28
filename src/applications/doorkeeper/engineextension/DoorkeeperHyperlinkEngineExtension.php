<?php

final class DoorkeeperHyperlinkEngineExtension
  extends PhabricatorRemarkupHyperlinkEngineExtension {

  const LINKENGINEKEY = 'doorkeeper';

  public function processHyperlinks(array $hyperlinks) {
    $engine = $this->getEngine();
    $viewer = $engine->getConfig('viewer');

    if (!$viewer) {
      return;
    }

    $configs = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer($viewer)
      ->withIsEnabled(true)
      ->execute();

    $providers = array();
    foreach ($configs as $key => $config) {
      $provider = $config->getProvider();
      if (($provider instanceof DoorkeeperRemarkupURIInterface)) {
        $providers[] = $provider;
      }
    }

    if (!$providers) {
      return;
    }

    $refs = array();
    foreach ($hyperlinks as $hyperlink) {
      $uri = $hyperlink->getURI();
      $uri = new PhutilURI($uri);

      foreach ($providers as $provider) {
        $ref = $provider->getDoorkeeperURIRef($uri);

        if (($ref !== null) && !($ref instanceof DoorkeeperURIRef)) {
          throw new Exception(
            pht(
              'Expected "getDoorkeeperURIRef()" to return "null" or an '.
              'object of type "DoorkeeperURIRef", but got %s from provider '.
              '"%s".',
              phutil_describe_type($ref),
              get_class($provider)));
        }

        if ($ref === null) {
          continue;
        }

        $tag_id = celerity_generate_unique_node_id();
        $href = phutil_string_cast($ref->getURI());

        $refs[] = array(
          'id' => $tag_id,
          'href' => $href,
          'ref' => array(
            $ref->getApplicationType(),
            $ref->getApplicationDomain(),
            $ref->getObjectType(),
            $ref->getObjectID(),
          ),
          'view' => $ref->getDisplayMode(),
        );

        $text = $ref->getText();
        if ($text === null) {
          $text = $href;
        }

        $view = id(new PHUITagView())
          ->setID($tag_id)
          ->setName($text)
          ->setHref($href)
          ->setType(PHUITagView::TYPE_OBJECT)
          ->setExternal(true);

        $hyperlink->setResult($view);
        break;
      }
    }

    if ($refs) {
      Javelin::initBehavior('doorkeeper-tag', array('tags' => $refs));
    }
  }

}
