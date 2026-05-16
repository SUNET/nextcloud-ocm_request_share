# SPDX-FileCopyrightText: Bernhard Posselt <dev@bernhard-posselt.com>
# SPDX-FileCopyrightText: 2026 SUNET <kano@sunet.se>
# SPDX-License-Identifier: AGPL-3.0-or-later

app_name=ocm_request_share
get_version = $(shell grep /version $(app_name)/appinfo/info.xml | sed 's/.*\([0-9]\.[0-9]\.[0-9]\).*/\1/')
project_dir=$(CURDIR)/$(app_name)
build_dir=$(project_dir)/build/artifacts
sign_dir=$(build_dir)/sign
version := $(call get_version)

.PHONY: all
all: appstore

.PHONY: clean
clean:
	rm -rf $(build_dir)

.PHONY: build
build:
ifneq (,$(wildcard $(project_dir)/composer.json))
	cd $(project_dir) && composer install --no-dev --prefer-dist --optimize-autoloader
endif
ifneq (,$(wildcard $(project_dir)/package.json))
	cd $(project_dir) && npm install && npm run build
endif

.PHONY: package
package: clean build
	mkdir -p $(sign_dir)
	rsync -a \
		--exclude=/build \
		--exclude=/tests \
		--exclude=.git \
		--exclude=.github \
		--exclude=.gitignore \
		--exclude=node_modules \
		--exclude=/Makefile \
		$(project_dir)/ $(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name)-$(version).tar.gz \
		-C $(sign_dir) $(app_name)

.PHONY: appstore
appstore: package
