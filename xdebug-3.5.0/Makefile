srcdir = /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0
builddir = /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0
top_srcdir = /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0
top_builddir = /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0
EGREP = /usr/bin/grep -E
SED = /usr/bin/sed
AWK = awk
SHLIB_SUFFIX_NAME = dylib
SHLIB_DL_SUFFIX_NAME = so
shared_objects_xdebug = xdebug.lo src/base/base.lo src/base/ctrl_socket.lo src/base/filter.lo src/lib/usefulstuff.lo src/lib/cmd_parser.lo src/lib/compat.lo src/lib/crc32.lo src/lib/file.lo src/lib/hash.lo src/lib/headers.lo src/lib/lib.lo src/lib/llist.lo src/lib/log.lo src/lib/normalize_path.lo src/lib/set.lo src/lib/str.lo src/lib/timing.lo src/lib/trim.lo src/lib/var.lo src/lib/var_export_html.lo src/lib/var_export_line.lo src/lib/var_export_text.lo src/lib/var_export_xml.lo src/lib/xdebug_strndup.lo src/lib/xml.lo src/lib/maps/maps.lo src/lib/maps/maps_private.lo src/lib/maps/parser.lo src/coverage/branch_info.lo src/coverage/code_coverage.lo src/debugger/com.lo src/debugger/debugger.lo src/debugger/handler_dbgp.lo src/debugger/handlers.lo src/debugger/ip_info.lo src/develop/develop.lo src/develop/monitor.lo src/develop/php_functions.lo src/develop/stack.lo src/develop/superglobals.lo src/gcstats/gc_stats.lo src/profiler/profiler.lo src/tracing/trace_computerized.lo src/tracing/trace_flamegraph.lo src/tracing/trace_html.lo src/tracing/trace_textual.lo src/tracing/tracing.lo
PHP_PECL_EXTENSION = xdebug
XDEBUG_SHARED_LIBADD = -lm
PHP_MODULES =
PHP_ZEND_EX = $(phplibdir)/xdebug.la
all_targets = $(PHP_MODULES) $(PHP_ZEND_EX)
install_targets = install-modules install-headers
prefix = /opt/homebrew/Cellar/php/8.5.2
exec_prefix = $(prefix)
libdir = ${exec_prefix}/lib
phpincludedir = /opt/homebrew/Cellar/php/8.5.2/include/php
CC = cc
CFLAGS = -g -O2
CFLAGS_CLEAN = $(CFLAGS) -D_GNU_SOURCE
CPP = cc -E
CPPFLAGS = -DHAVE_CONFIG_H
CXX =
CXXFLAGS =
CXXFLAGS_CLEAN = $(CXXFLAGS)
EXTENSION_DIR = /opt/homebrew/Cellar/php/8.5.2/pecl/20250925
PHP_EXECUTABLE = /opt/homebrew/Cellar/php/8.5.2/bin/php
EXTRA_LDFLAGS =
EXTRA_LIBS =
INCLUDES = -I/opt/homebrew/Cellar/php/8.5.2/include/php -I/opt/homebrew/Cellar/php/8.5.2/include/php/main -I/opt/homebrew/Cellar/php/8.5.2/include/php/TSRM -I/opt/homebrew/Cellar/php/8.5.2/include/php/Zend -I/opt/homebrew/Cellar/php/8.5.2/include/php/ext -I/opt/homebrew/Cellar/php/8.5.2/include/php/ext/date/lib -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src
LDFLAGS =
LIBTOOL = $(SHELL) $(top_builddir)/libtool
SHELL = /bin/sh
INSTALL_HEADERS =
BUILD_CC = cc
phplibdir = /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/modules
mkinstalldirs = $(top_srcdir)/build/shtool mkdir -p
INSTALL = $(top_srcdir)/build/shtool install -c
INSTALL_DATA = $(INSTALL) -m 644

COMMON_FLAGS = $(INCLUDES) $(EXTRA_INCLUDES) $(CPPFLAGS) $(PHP_FRAMEWORKPATH)

all: $(all_targets)
	@echo
	@echo "Build complete."
	@echo "Don't forget to run 'make test'."
	@echo

build-modules: $(PHP_MODULES) $(PHP_ZEND_EX)

build-binaries: $(PHP_BINARIES)

libphp.la: $(PHP_GLOBAL_OBJS) $(PHP_SAPI_OBJS)
	$(LIBTOOL) --tag=CC --mode=link $(CC) $(LIBPHP_CFLAGS) $(CFLAGS) $(EXTRA_CFLAGS) -rpath $(phptempdir) $(EXTRA_LDFLAGS) $(LDFLAGS) $(PHP_RPATHS) $(PHP_GLOBAL_OBJS) $(PHP_SAPI_OBJS) $(EXTRA_LIBS) $(ZEND_EXTRA_LIBS) -o $@
	-@$(LIBTOOL) --tag=CC --mode=install cp $@ $(phptempdir)/$@ >/dev/null 2>&1

libphp.dylib: libphp.la
	$(LIBTOOL) --tag=CC --mode=link $(CC) -dynamiclib $(LIBPHP_CFLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS) -rpath $(phptempdir) -install_name @rpath/$@ $(EXTRA_LDFLAGS) $(LDFLAGS) $(PHP_RPATHS) $(PHP_GLOBAL_OBJS) $(PHP_SAPI_OBJS) $(EXTRA_LIBS) $(ZEND_EXTRA_LIBS) -o $@
	-@$(LIBTOOL) --silent --tag=CC --mode=install cp $@ $(phptempdir)/$@ >/dev/null 2>&1

libs/libphp.bundle: $(PHP_GLOBAL_OBJS) $(PHP_SAPI_OBJS)
	$(CC) $(MH_BUNDLE_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS) $(LDFLAGS) $(EXTRA_LDFLAGS) $(PHP_GLOBAL_OBJS:.lo=.o) $(PHP_SAPI_OBJS:.lo=.o) $(PHP_FRAMEWORKS) $(EXTRA_LIBS) $(ZEND_EXTRA_LIBS) -o $@ && cp $@ libs/libphp.so

install: $(all_targets) $(install_targets)

install-sapi: $(OVERALL_TARGET)
	@echo "Installing PHP SAPI module:       $(PHP_SAPI)"
	-@$(mkinstalldirs) $(INSTALL_ROOT)$(bindir)
	-@if test ! -r $(phptempdir)/libphp.$(SHLIB_DL_SUFFIX_NAME); then \
		for i in 0.0.0 0.0 0; do \
			if test -r $(phptempdir)/libphp.$(SHLIB_DL_SUFFIX_NAME).$$i; then \
				$(LN_S) $(phptempdir)/libphp.$(SHLIB_DL_SUFFIX_NAME).$$i $(phptempdir)/libphp.$(SHLIB_DL_SUFFIX_NAME); \
				break; \
			fi; \
		done; \
	fi
	@$(INSTALL_IT)

install-binaries: build-binaries $(install_binary_targets)

install-modules: build-modules
	@test -d modules && \
	$(mkinstalldirs) $(INSTALL_ROOT)$(EXTENSION_DIR)
	@echo "Installing shared extensions:     $(INSTALL_ROOT)$(EXTENSION_DIR)/"
	@rm -f modules/*.la >/dev/null 2>&1
	@$(INSTALL) modules/* $(INSTALL_ROOT)$(EXTENSION_DIR)

install-headers:
	-@if test "$(INSTALL_HEADERS)"; then \
		for i in `echo $(INSTALL_HEADERS)`; do \
			i=`$(top_srcdir)/build/shtool path -d $$i`; \
			paths="$$paths $(INSTALL_ROOT)$(phpincludedir)/$$i"; \
		done; \
		$(mkinstalldirs) $$paths && \
		echo "Installing header files:          $(INSTALL_ROOT)$(phpincludedir)/" && \
		for i in `echo $(INSTALL_HEADERS)`; do \
			if test "$(PHP_PECL_EXTENSION)"; then \
				src=`echo $$i | $(SED) -e "s#ext/$(PHP_PECL_EXTENSION)/##g"`; \
			else \
				src=$$i; \
			fi; \
			if test -f "$(top_srcdir)/$$src"; then \
				$(INSTALL_DATA) $(top_srcdir)/$$src $(INSTALL_ROOT)$(phpincludedir)/$$i; \
			elif test -f "$(top_builddir)/$$src"; then \
				$(INSTALL_DATA) $(top_builddir)/$$src $(INSTALL_ROOT)$(phpincludedir)/$$i; \
			else \
				(cd $(top_srcdir)/$$src && $(INSTALL_DATA) *.h $(INSTALL_ROOT)$(phpincludedir)/$$i; \
				cd $(top_builddir)/$$src && $(INSTALL_DATA) *.h $(INSTALL_ROOT)$(phpincludedir)/$$i) 2>/dev/null || true; \
			fi \
		done; \
	fi

PHP_TEST_SETTINGS = -d 'open_basedir=' -d 'output_buffering=0' -d 'memory_limit=-1'
PHP_TEST_SHARED_EXTENSIONS =  ` \
	if test "x$(PHP_MODULES)" != "x"; then \
		for i in $(PHP_MODULES)""; do \
			. $$i; \
			if test "x$$dlname" != "xdl_test.so"; then \
				$(top_srcdir)/build/shtool echo -n -- " -d extension=$$dlname"; \
			fi; \
		done; \
	fi; \
	if test "x$(PHP_ZEND_EX)" != "x"; then \
		for i in $(PHP_ZEND_EX)""; do \
			. $$i; $(top_srcdir)/build/shtool echo -n -- " -d zend_extension=$(top_builddir)/modules/$$dlname"; \
		done; \
	fi`
PHP_DEPRECATED_DIRECTIVES_REGEX = '^[\t\ ]*(magic_quotes_(gpc|runtime|sybase)?|(zend_)?extension(_debug)?(_ts)?|session\.sid_(length|bits_per_character))[\t\ ]*='

test: all
	@if test ! -z "$(PHP_EXECUTABLE)" && test -x "$(PHP_EXECUTABLE)"; then \
		INI_FILE=`$(PHP_EXECUTABLE) -d 'display_errors=stderr' -r 'echo php_ini_loaded_file();' 2> /dev/null`; \
		if test "$$INI_FILE"; then \
			$(EGREP) -h -v $(PHP_DEPRECATED_DIRECTIVES_REGEX) "$$INI_FILE" > $(top_builddir)/tmp-php.ini; \
		else \
			echo > $(top_builddir)/tmp-php.ini; \
		fi; \
		INI_SCANNED_PATH=`$(PHP_EXECUTABLE) -d 'display_errors=stderr' -r '$$a = explode(",\n", trim(php_ini_scanned_files())); echo $$a[0];' 2> /dev/null`; \
		if test "$$INI_SCANNED_PATH"; then \
			INI_SCANNED_PATH=`$(top_srcdir)/build/shtool path -d $$INI_SCANNED_PATH`; \
			$(EGREP) -h -v $(PHP_DEPRECATED_DIRECTIVES_REGEX) "$$INI_SCANNED_PATH"/*.ini >> $(top_builddir)/tmp-php.ini; \
		fi; \
		TEST_PHP_EXECUTABLE=$(PHP_EXECUTABLE) \
		TEST_PHP_SRCDIR=$(top_srcdir) \
		CC="$(CC)" \
			$(PHP_EXECUTABLE) -n -c $(top_builddir)/tmp-php.ini $(PHP_TEST_SETTINGS) $(top_srcdir)/run-tests.php -n -c $(top_builddir)/tmp-php.ini -d extension_dir=$(top_builddir)/modules/ $(PHP_TEST_SHARED_EXTENSIONS) $(TESTS); \
		TEST_RESULT_EXIT_CODE=$$?; \
		rm $(top_builddir)/tmp-php.ini; \
		exit $$TEST_RESULT_EXIT_CODE; \
	else \
		echo "ERROR: Cannot run tests without CLI sapi."; \
	fi

clean:
	find . -name \*.gcno -o -name \*.gcda | xargs rm -f
	find . -name \*.lo -o -name \*.o -o -name \*.dep | xargs rm -f
	find . -name \*.la -o -name \*.a | xargs rm -f
	find . -name \*.so | xargs rm -f
	find . -name .libs -a -type d|xargs rm -rf
	rm -f libphp.la $(SAPI_CLI_PATH) $(SAPI_CGI_PATH) $(SAPI_LITESPEED_PATH) $(SAPI_FPM_PATH) $(SAPI_PHPDBG_PATH) $(SAPI_PHPDBG_SHARED_PATH) $(OVERALL_TARGET) modules/* libs/*
	rm -f ext/opcache/jit/ir/gen_ir_fold_hash
	rm -f ext/opcache/jit/ir/minilua
	rm -f ext/opcache/jit/ir/ir_fold_hash.h
	rm -f ext/opcache/jit/ir/ir_emit_x86.h
	rm -f ext/opcache/jit/ir/ir_emit_aarch64.h

distclean: clean
	rm -f Makefile config.cache config.log config.status Makefile.objects Makefile.fragments libtool main/php_config.h main/internal_functions_cli.c main/internal_functions.c Zend/zend_dtrace_gen.h Zend/zend_dtrace_gen.h.bak Zend/zend_config.h
	rm -f main/build-defs.h scripts/phpize
	rm -f ext/date/lib/timelib_config.h ext/mbstring/libmbfl/config.h
	rm -f scripts/man1/phpize.1 scripts/php-config scripts/man1/php-config.1 sapi/cli/php.1 sapi/cgi/php-cgi.1 sapi/phpdbg/phpdbg.1 ext/phar/phar.1 ext/phar/phar.phar.1
	rm -f sapi/fpm/php-fpm.conf sapi/fpm/init.d.php-fpm sapi/fpm/php-fpm.service sapi/fpm/php-fpm.8 sapi/fpm/status.html
	rm -f ext/phar/phar.phar ext/phar/phar.php
	if test "$(srcdir)" != "$(builddir)"; then \
		rm -f ext/phar/phar/phar.inc; \
	fi

prof-gen:
	CCACHE_DISABLE=1 $(MAKE) PROF_FLAGS=-fprofile-generate all
	find . -name \*.gcda | xargs rm -f

prof-clean:
	find . -name \*.lo -o -name \*.o | xargs rm -f
	find . -name \*.la -o -name \*.a | xargs rm -f
	find . -name \*.so | xargs rm -f
	rm -f libphp.la $(SAPI_CLI_PATH) $(SAPI_CGI_PATH) $(SAPI_LITESPEED_PATH) $(SAPI_FPM_PATH) $(SAPI_PHPDBG_PATH) $(SAPI_PHPDBG_SHARED_PATH) $(OVERALL_TARGET) modules/* libs/*

prof-use:
	CCACHE_DISABLE=1 $(MAKE) PROF_FLAGS=-fprofile-use all

%_arginfo.h: %.stub.php
	@if test -e "$(top_srcdir)/build/gen_stub.php"; then \
		if test ! -z "$(PHP)"; then \
			echo Parse $< to generate $@;\
			$(PHP) $(top_srcdir)/build/gen_stub.php $<; \
		elif test ! -z "$(PHP_EXECUTABLE)" && test -x "$(PHP_EXECUTABLE)"; then \
			echo Parse $< to generate $@;\
			$(PHP_EXECUTABLE) $(top_srcdir)/build/gen_stub.php $<; \
		fi; \
	fi;

.PHONY: all clean install distclean test prof-gen prof-clean prof-use
install: $(all_targets) $(install_targets) show-install-instructions

show-install-instructions:
	@echo
	@$(top_srcdir)/build/shtool echo -n -e %B
	@echo   "  +----------------------------------------------------------------------+"
	@echo   "  |                                                                      |"
	@echo   "  |   INSTALLATION INSTRUCTIONS                                          |"
	@echo   "  |   =========================                                          |"
	@echo   "  |                                                                      |"
	@echo   "  |   See https://xdebug.org/install.php#configure-php for instructions  |"
	@echo   "  |   on how to enable Xdebug for PHP.                                   |"
	@echo   "  |                                                                      |"
	@echo   "  |   Documentation is available online as well:                         |"
	@echo   "  |   - A list of all settings:  https://xdebug.org/docs-settings.php    |"
	@echo   "  |   - A list of all functions: https://xdebug.org/docs-functions.php   |"
	@echo   "  |   - Profiling instructions:  https://xdebug.org/docs-profiling2.php  |"
	@echo   "  |   - Remote debugging:        https://xdebug.org/docs-debugger.php    |"
	@echo   "  |                                                                      |"
	@echo   "  |                                                                      |"
	@echo   "  |   NOTE: Please disregard the message                                 |"
	@echo   "  |       You should add \"extension=xdebug.so\" to php.ini                |"
	@echo   "  |   that is emitted by the PECL installer. This does not work for      |"
	@echo   "  |   Xdebug.                                                            |"
	@echo   "  |                                                                      |"
	@echo   "  +----------------------------------------------------------------------+"
	@$(top_srcdir)/build/shtool echo -n -e %b
	@echo
	@echo

findphp:
	@echo $(PHP_EXECUTABLE)

clean-tests:
	rm -f tests/*.diff tests/*.exp tests/*.log tests/*.out tests/*.php tests/*.sh tests/*.mem

test:
	@echo "Xdebug can not be tested with 'make test', please refer to:"
	@echo "    https://github.com/xdebug/xdebug#testing"
	@echo

test-coverage:
	$(MAKE) clean
	CCACHE_DISABLE=1 EXTRA_CFLAGS=--coverage $(MAKE) all
	TEST_PHP_ARGS="-n -d zend_extension=$(top_srcdir)/.libs/xdebug.so" php run-xdebug-tests.php

test-coverage-lcov: test-coverage
	lcov -c --directory . --output-file $(top_srcdir)/.coverage.lcov

test-coverage-html: test-coverage-lcov
	genhtml $(top_srcdir)/.coverage.lcov --output-directory=/tmp/html
-include xdebug.dep
xdebug.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/xdebug.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/xdebug.c -o xdebug.lo  -MMD -MF xdebug.dep -MT xdebug.lo
-include src/base/base.dep
src/base/base.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/base/base.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/base/base.c -o src/base/base.lo  -MMD -MF src/base/base.dep -MT src/base/base.lo
-include src/base/ctrl_socket.dep
src/base/ctrl_socket.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/base/ctrl_socket.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/base/ctrl_socket.c -o src/base/ctrl_socket.lo  -MMD -MF src/base/ctrl_socket.dep -MT src/base/ctrl_socket.lo
-include src/base/filter.dep
src/base/filter.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/base/filter.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/base/filter.c -o src/base/filter.lo  -MMD -MF src/base/filter.dep -MT src/base/filter.lo
-include src/lib/usefulstuff.dep
src/lib/usefulstuff.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/usefulstuff.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/usefulstuff.c -o src/lib/usefulstuff.lo  -MMD -MF src/lib/usefulstuff.dep -MT src/lib/usefulstuff.lo
-include src/lib/cmd_parser.dep
src/lib/cmd_parser.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/cmd_parser.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/cmd_parser.c -o src/lib/cmd_parser.lo  -MMD -MF src/lib/cmd_parser.dep -MT src/lib/cmd_parser.lo
-include src/lib/compat.dep
src/lib/compat.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/compat.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/compat.c -o src/lib/compat.lo  -MMD -MF src/lib/compat.dep -MT src/lib/compat.lo
-include src/lib/crc32.dep
src/lib/crc32.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/crc32.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/crc32.c -o src/lib/crc32.lo  -MMD -MF src/lib/crc32.dep -MT src/lib/crc32.lo
-include src/lib/file.dep
src/lib/file.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/file.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/file.c -o src/lib/file.lo  -MMD -MF src/lib/file.dep -MT src/lib/file.lo
-include src/lib/hash.dep
src/lib/hash.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/hash.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/hash.c -o src/lib/hash.lo  -MMD -MF src/lib/hash.dep -MT src/lib/hash.lo
-include src/lib/headers.dep
src/lib/headers.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/headers.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/headers.c -o src/lib/headers.lo  -MMD -MF src/lib/headers.dep -MT src/lib/headers.lo
-include src/lib/lib.dep
src/lib/lib.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/lib.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/lib.c -o src/lib/lib.lo  -MMD -MF src/lib/lib.dep -MT src/lib/lib.lo
-include src/lib/llist.dep
src/lib/llist.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/llist.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/llist.c -o src/lib/llist.lo  -MMD -MF src/lib/llist.dep -MT src/lib/llist.lo
-include src/lib/log.dep
src/lib/log.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/log.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/log.c -o src/lib/log.lo  -MMD -MF src/lib/log.dep -MT src/lib/log.lo
-include src/lib/normalize_path.dep
src/lib/normalize_path.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/normalize_path.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/normalize_path.c -o src/lib/normalize_path.lo  -MMD -MF src/lib/normalize_path.dep -MT src/lib/normalize_path.lo
-include src/lib/set.dep
src/lib/set.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/set.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/set.c -o src/lib/set.lo  -MMD -MF src/lib/set.dep -MT src/lib/set.lo
-include src/lib/str.dep
src/lib/str.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/str.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/str.c -o src/lib/str.lo  -MMD -MF src/lib/str.dep -MT src/lib/str.lo
-include src/lib/timing.dep
src/lib/timing.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/timing.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/timing.c -o src/lib/timing.lo  -MMD -MF src/lib/timing.dep -MT src/lib/timing.lo
-include src/lib/trim.dep
src/lib/trim.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/trim.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/trim.c -o src/lib/trim.lo  -MMD -MF src/lib/trim.dep -MT src/lib/trim.lo
-include src/lib/var.dep
src/lib/var.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/var.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/var.c -o src/lib/var.lo  -MMD -MF src/lib/var.dep -MT src/lib/var.lo
-include src/lib/var_export_html.dep
src/lib/var_export_html.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/var_export_html.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/var_export_html.c -o src/lib/var_export_html.lo  -MMD -MF src/lib/var_export_html.dep -MT src/lib/var_export_html.lo
-include src/lib/var_export_line.dep
src/lib/var_export_line.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/var_export_line.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/var_export_line.c -o src/lib/var_export_line.lo  -MMD -MF src/lib/var_export_line.dep -MT src/lib/var_export_line.lo
-include src/lib/var_export_text.dep
src/lib/var_export_text.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/var_export_text.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/var_export_text.c -o src/lib/var_export_text.lo  -MMD -MF src/lib/var_export_text.dep -MT src/lib/var_export_text.lo
-include src/lib/var_export_xml.dep
src/lib/var_export_xml.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/var_export_xml.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/var_export_xml.c -o src/lib/var_export_xml.lo  -MMD -MF src/lib/var_export_xml.dep -MT src/lib/var_export_xml.lo
-include src/lib/xdebug_strndup.dep
src/lib/xdebug_strndup.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/xdebug_strndup.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/xdebug_strndup.c -o src/lib/xdebug_strndup.lo  -MMD -MF src/lib/xdebug_strndup.dep -MT src/lib/xdebug_strndup.lo
-include src/lib/xml.dep
src/lib/xml.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/xml.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/xml.c -o src/lib/xml.lo  -MMD -MF src/lib/xml.dep -MT src/lib/xml.lo
-include src/lib/maps/maps.dep
src/lib/maps/maps.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/maps/maps.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/maps/maps.c -o src/lib/maps/maps.lo  -MMD -MF src/lib/maps/maps.dep -MT src/lib/maps/maps.lo
-include src/lib/maps/maps_private.dep
src/lib/maps/maps_private.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/maps/maps_private.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/maps/maps_private.c -o src/lib/maps/maps_private.lo  -MMD -MF src/lib/maps/maps_private.dep -MT src/lib/maps/maps_private.lo
-include src/lib/maps/parser.dep
src/lib/maps/parser.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/maps/parser.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/lib/maps/parser.c -o src/lib/maps/parser.lo  -MMD -MF src/lib/maps/parser.dep -MT src/lib/maps/parser.lo
-include src/coverage/branch_info.dep
src/coverage/branch_info.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/coverage/branch_info.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/coverage/branch_info.c -o src/coverage/branch_info.lo  -MMD -MF src/coverage/branch_info.dep -MT src/coverage/branch_info.lo
-include src/coverage/code_coverage.dep
src/coverage/code_coverage.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/coverage/code_coverage.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/coverage/code_coverage.c -o src/coverage/code_coverage.lo  -MMD -MF src/coverage/code_coverage.dep -MT src/coverage/code_coverage.lo
-include src/debugger/com.dep
src/debugger/com.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/debugger/com.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/debugger/com.c -o src/debugger/com.lo  -MMD -MF src/debugger/com.dep -MT src/debugger/com.lo
-include src/debugger/debugger.dep
src/debugger/debugger.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/debugger/debugger.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/debugger/debugger.c -o src/debugger/debugger.lo  -MMD -MF src/debugger/debugger.dep -MT src/debugger/debugger.lo
-include src/debugger/handler_dbgp.dep
src/debugger/handler_dbgp.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/debugger/handler_dbgp.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/debugger/handler_dbgp.c -o src/debugger/handler_dbgp.lo  -MMD -MF src/debugger/handler_dbgp.dep -MT src/debugger/handler_dbgp.lo
-include src/debugger/handlers.dep
src/debugger/handlers.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/debugger/handlers.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/debugger/handlers.c -o src/debugger/handlers.lo  -MMD -MF src/debugger/handlers.dep -MT src/debugger/handlers.lo
-include src/debugger/ip_info.dep
src/debugger/ip_info.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/debugger/ip_info.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/debugger/ip_info.c -o src/debugger/ip_info.lo  -MMD -MF src/debugger/ip_info.dep -MT src/debugger/ip_info.lo
-include src/develop/develop.dep
src/develop/develop.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/develop/develop.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/develop/develop.c -o src/develop/develop.lo  -MMD -MF src/develop/develop.dep -MT src/develop/develop.lo
-include src/develop/monitor.dep
src/develop/monitor.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/develop/monitor.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/develop/monitor.c -o src/develop/monitor.lo  -MMD -MF src/develop/monitor.dep -MT src/develop/monitor.lo
-include src/develop/php_functions.dep
src/develop/php_functions.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/develop/php_functions.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/develop/php_functions.c -o src/develop/php_functions.lo  -MMD -MF src/develop/php_functions.dep -MT src/develop/php_functions.lo
-include src/develop/stack.dep
src/develop/stack.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/develop/stack.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/develop/stack.c -o src/develop/stack.lo  -MMD -MF src/develop/stack.dep -MT src/develop/stack.lo
-include src/develop/superglobals.dep
src/develop/superglobals.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/develop/superglobals.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/develop/superglobals.c -o src/develop/superglobals.lo  -MMD -MF src/develop/superglobals.dep -MT src/develop/superglobals.lo
-include src/gcstats/gc_stats.dep
src/gcstats/gc_stats.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/gcstats/gc_stats.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/gcstats/gc_stats.c -o src/gcstats/gc_stats.lo  -MMD -MF src/gcstats/gc_stats.dep -MT src/gcstats/gc_stats.lo
-include src/profiler/profiler.dep
src/profiler/profiler.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/profiler/profiler.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/profiler/profiler.c -o src/profiler/profiler.lo  -MMD -MF src/profiler/profiler.dep -MT src/profiler/profiler.lo
-include src/tracing/trace_computerized.dep
src/tracing/trace_computerized.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/tracing/trace_computerized.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/tracing/trace_computerized.c -o src/tracing/trace_computerized.lo  -MMD -MF src/tracing/trace_computerized.dep -MT src/tracing/trace_computerized.lo
-include src/tracing/trace_flamegraph.dep
src/tracing/trace_flamegraph.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/tracing/trace_flamegraph.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/tracing/trace_flamegraph.c -o src/tracing/trace_flamegraph.lo  -MMD -MF src/tracing/trace_flamegraph.dep -MT src/tracing/trace_flamegraph.lo
-include src/tracing/trace_html.dep
src/tracing/trace_html.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/tracing/trace_html.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/tracing/trace_html.c -o src/tracing/trace_html.lo  -MMD -MF src/tracing/trace_html.dep -MT src/tracing/trace_html.lo
-include src/tracing/trace_textual.dep
src/tracing/trace_textual.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/tracing/trace_textual.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/tracing/trace_textual.c -o src/tracing/trace_textual.lo  -MMD -MF src/tracing/trace_textual.dep -MT src/tracing/trace_textual.lo
-include src/tracing/tracing.dep
src/tracing/tracing.lo: /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/tracing/tracing.c
	$(LIBTOOL) --tag=CC --mode=compile $(CC) -I. -I/Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0 $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS)    -DZEND_COMPILE_DL_EXT=1 -c /Users/paulramotowski/Documents/GitHub/rd-post-republishing/xdebug-3.5.0/src/tracing/tracing.c -o src/tracing/tracing.lo  -MMD -MF src/tracing/tracing.dep -MT src/tracing/tracing.lo
$(phplibdir)/xdebug.la: ./xdebug.la
	$(LIBTOOL) --tag=CC --mode=install cp ./xdebug.la $(phplibdir)

./xdebug.la: $(shared_objects_xdebug) $(XDEBUG_SHARED_DEPENDENCIES)
	$(LIBTOOL) --tag=CC --mode=link $(CC) -shared $(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS) $(LDFLAGS)  -o $@ -export-dynamic -avoid-version -prefer-pic -module -rpath $(phplibdir) $(EXTRA_LDFLAGS) $(shared_objects_xdebug) $(XDEBUG_SHARED_LIBADD)

