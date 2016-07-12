<?php

final class CelerityResourceTransformerTestCase extends PhabricatorTestCase {

  public function testTransformation() {
    $files = dirname(__FILE__).'/transformer/';
    foreach (Filesystem::listDirectory($files) as $file) {
      $name = basename($file);
      $data = Filesystem::readFile($files.'/'.$file);
      $parts = preg_split('/^~~~+\n/m', $data);
      $parts = array_merge($parts, array(null));

      list($options, $in, $expect) = $parts;

      $parser = new PhutilSimpleOptions();
      $options = $parser->parse($options) + array(
        'minify' => false,
        'name'   => $name,
      );

      $xformer = new CelerityResourceTransformer();
      $xformer->setRawURIMap(
        array(
          '/rsrc/example.png' => '/res/hash/example.png',
        ));
      $xformer->setMinify($options['minify']);

      $result = $xformer->transformResource($options['name'], $in);

      $this->assertEqual(rtrim($expect), rtrim($result), $file);
    }
  }

}
