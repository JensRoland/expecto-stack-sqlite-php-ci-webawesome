#* Post-installation tasks
.PHONY: post-install
post-install:
	( cd spcw && chmod -R a+w writable && php -r "file_exists('.env') || copy('env', '.env');" && php spark key:generate && php spark db:create database )

#* Local development server
.PHONY: serve
serve:
	( cd spcw && php spark serve )
