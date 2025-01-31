#* Post-installation tasks
.PHONY: post-install
post-install:
	( cd spcw && chmod -R a+w writable && php -r "file_exists('.env') || copy('env', '.env');" && php spark key:generate && php spark db:create database )

#* Local development server
.PHONY: serve
serve:
	( cd spcw && php spark serve )

#* Run database migrations
.PHONY: migrate
migrate:
	( cd spcw && php spark migrate )

#* Bootstrap demo application
.PHONY: bootstrap-demo-app
bootstrap-demo-app:
	( cd spcw && php spark make:crud ../demo-app-spec.json --migration --seeder --entity --dates --force )

#* Run demo database seeder
.PHONY: seed-demo-database
seed-demo-database:
	( cd spcw && php spark db:seed BlogPostSeeder )
