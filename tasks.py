# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.
import json
import os

from invoke import task

DOCKER_IMAGE_NAME = os.getenv('DOCKERHUB_REPO', 'mozilla/phabricator')


@task
def version(ctx):
    """Print version information in JSON format."""
    print(json.dumps({
        'moz_phabricator_commit':
        os.getenv('CIRCLE_SHA1', None),
        'moz_phabricator_version':
        os.getenv('CIRCLE_SHA1', None),
        'moz_phabricator_source':
        'https://github.com/%s/%s' % (
            os.getenv('CIRCLE_PROJECT_USERNAME', 'mozilla-conduit'),
            os.getenv('CIRCLE_PROJECT_REPONAME', 'phabricator')
        ),
        'build':
        os.getenv('CIRCLE_BUILD_URL', None),
    }))


@task
def build(ctx):
    """Build the docker image."""
    ctx.run('docker build --pull -t {image_name} --target production .'.format(
        image_name=DOCKER_IMAGE_NAME
    ))


@task
def imageid(ctx):
    """Print the built docker image ID."""
    ctx.run("docker inspect -f '{format}' {image_name}".format(
        image_name=DOCKER_IMAGE_NAME,
        format='{{.Id}}'
    ))


@task
def buildtest(ctx):
    """Test phabricator extensions."""
    ctx.run("docker-compose build test_phab")


@task
def test(ctx):
    """Test phabricator extensions."""
    ctx.run("docker-compose run test_phab")


@task
def liberate(ctx):
    """Update phutil_map."""
    ctx.run("docker-compose run --rm test_phab arc_liberate")
