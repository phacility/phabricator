# Twilio API helper library.
# See LICENSE file for copyright and license details.

define LICENSE
<?php

/**
 * Twilio API helper library.
 *
 * @category  Services
 * @package   Services_Twilio
 * @author    Neuman Vong <neuman@twilio.com>
 * @license   http://creativecommons.org/licenses/MIT/ MIT
 * @link      http://pear.php.net/package/Services_Twilio
 */
endef
export LICENSE

COMPOSER = $(shell which composer)
ifeq ($(strip $(COMPOSER)),)
	COMPOSER = php composer.phar
endif

all: test

clean:
	@rm -rf dist venv

PHP_FILES = `find dist -name \*.php`
dist: clean
	@mkdir dist
	@git archive master | (cd dist; tar xf -)
	@for php in $(PHP_FILES); do\
	  echo "$$LICENSE" > $$php.new; \
	  tail -n+2 $$php >> $$php.new; \
	  mv $$php.new $$php; \
	done

test-install:
	# Composer: http://getcomposer.org/download/
	$(COMPOSER) install

install:
	pear channel-discover twilio.github.com/pear
	pear install twilio/Services_Twilio

# if these fail, you may need to install the helper library - run "make
# test-install"
test:
	@PATH=vendor/bin:$(PATH) phpunit --strict --colors --configuration tests/phpunit.xml;

venv:
	virtualenv venv

docs-install: venv
	. venv/bin/activate; pip install -r docs/requirements.txt

docs:
	. venv/bin/activate; cd docs && make html

release-install:
	pear channel-discover twilio.github.com/pear || true
	pear channel-discover pear.pirum-project.org || true
	pear install pirum/Pirum || true
	pear install XML_Serializer-0.20.2 || true
	pear install PEAR_PackageFileManager2 || true

authors:
	echo "Authors\n=======\n\nA huge thanks to all of our contributors:\n\n" > AUTHORS.md
	git log --raw | grep "^Author: " | cut -d ' ' -f2- | cut -d '<' -f1 | sed 's/^/- /' | sort | uniq >> AUTHORS.md

.PHONY: all clean dist test docs docs-install test-install authors
