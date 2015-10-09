## Fetching a site

Fetching (you may have guessed) is Fetcher's speciality! Fetching is the process of getting a copy of a site running on your machine, or updating a copy you already have.

For example, if your teammate asks you to do some work on a project you've never worked on before, you would say "One minute, just need to fetch a copy on my local."

`drush fetch foo` fetches the site 'foo', where 'foo' is a site known by your Fetcher Server. It gets the code, creates the folder layout (see 'Creating a Site'), adds an Apache VirtualHost, and creates a Drush alias.

You can optionally in addition sync the database and/or files. On large sites its best to not sync the files and instead rely on the Stage File Proxy Drupal module to get the files you need for development. 
Typically you will want the database the first time you fetch the site and then periodically to update it. You may want to get the database from an environment other than production to avoid a performance hit on your live environment. To get the database, use
```
drush fetch foo --sql-sync
```

There are lots of options for `drush fetch`, please see `drush help fetch`.

You can use fetcher not only to run local copies of sites but to create new environments for your sites (spin up a staging site on your staging server, or start up the live site). Make sure as you add environments that you keep your Fetcher Server up to date with the info of each environment a site has.

### Fetching a site hosted on Acquia or Pantheon

You can include sites hosted on services like Acquia or Pantheon in your Fetcher Server and fetch them. One catch is that these services include `settings.php` files in their git repositories. This practice clashes with Fetcher's approach of creating its own `settings.php`. You can work around this by using the --site option with fetch.

For example,

```
drush fetch foo --site=foo.local
```
will fetch the site foo and create a `settings.php` file within sites/foo.local rather than sites/default. When you view the site at foo.local, the `foo.local/settings.php` file will be used. 

When you use drush commands on a site which uses a `settings.php` that is not in default, you have to let drush know the context. You can do that via `drushrc.php`, by using drush aliases (`drush @foo.local status`) or by `cd`ing into `sites/foo.local`.
