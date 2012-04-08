<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group celerity
 */
final class CelerityResourceTransformerTestCase extends PhabricatorTestCase {

  public function testTransformation() {
    $files = dirname(__FILE__).'/data/';
    foreach (Filesystem::listDirectory($files) as $file) {
      $name = basename($file);
      $data = Filesystem::readFile($files.'/'.$file);
      $parts = preg_split('/^~~~+\n/m', $data);
      $parts = array_merge($parts, array(null));

      list($options, $in, $expect) = $parts;

      $options = PhutilSimpleOptions::parse($options) + array(
        'minify' => false,
        'name'   => $name,
      );

      $xformer = new CelerityResourceTransformer();
      $xformer->setRawResourceMap(
        array(
          '/rsrc/example.png' => array(
            'uri' => '/res/hash/example.png',
          ),
        ));
      $xformer->setMinify($options['minify']);

      $result = $xformer->transformResource($options['name'], $in);

      $this->assertEqual(rtrim($expect), rtrim($result), $file);
    }
  }

}
