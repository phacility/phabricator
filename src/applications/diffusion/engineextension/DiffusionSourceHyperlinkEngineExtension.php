<?php

final class DiffusionSourceHyperlinkEngineExtension
  extends PhabricatorRemarkupHyperlinkEngineExtension {

  const LINKENGINEKEY = 'diffusion-src';

  public function processHyperlinks(array $hyperlinks) {
    $engine = $this->getEngine();
    $viewer = $engine->getConfig('viewer');

    if (!$viewer) {
      return;
    }

    $hyperlinks = $this->getSelfLinks($hyperlinks);

    $links = array();
    foreach ($hyperlinks as $link) {
      $uri = $link->getURI();
      $uri = new PhutilURI($uri);

      $path = $uri->getPath();

      $pattern =
        '(^'.
        '/(?:diffusion|source)'.
        '/(?P<identifier>[^/]+)'.
        '/browse'.
        '/(?P<blob>.*)'.
        '\z)';
      $matches = null;
      if (!preg_match($pattern, $path, $matches)) {
        continue;
      }

      $links[] = array(
        'ref' => $link,
        'identifier' => $matches['identifier'],
        'blob' => $matches['blob'],
      );
    }

    if (!$links) {
      return;
    }

    $identifiers = ipull($links, 'identifier');

    $query = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withIdentifiers($identifiers);

    $query->execute();

    $repository_map = $query->getIdentifierMap();

    foreach ($links as $link) {
      $identifier = $link['identifier'];

      $repository = idx($repository_map, $identifier);
      if (!$repository) {
        continue;
      }

      $ref = $link['ref'];
      $uri = $ref->getURI();


      $tag = id(new DiffusionSourceLinkView())
        ->setViewer($viewer)
        ->setRepository($repository)
        ->setURI($uri)
        ->setBlob($link['blob']);

      if (!$ref->isEmbed()) {
        $tag->setText($uri);
      }

      $ref->setResult($tag);
    }
  }

}
