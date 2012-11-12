<?php

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
