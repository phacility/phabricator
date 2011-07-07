<?php

/*
 * Copyright 2011 Facebook, Inc.
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
 * Registers a resource map for Celerity. This is glue code between the Celerity
 * mapper script and @{class:CelerityResourceMap}.
 *
 * @group celerity
 */
function celerity_register_resource_map(array $map, array $package_map) {
  $instance = CelerityResourceMap::getInstance();
  $instance->setResourceMap($map);
  $instance->setPackageMap($package_map);
}
