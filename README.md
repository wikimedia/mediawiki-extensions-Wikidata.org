Wikidata.org
============

This extension is a grab-bag for miscellaneous configuration and customizations that are specific to wikidata.org.

This includes the following things:
1. It adds styles and images for Sitelink badges specific to Wikidata.
2. It adds a link about [DataAccess](https://www.wikidata.org/wiki/Special:MyLanguage/Wikidata:Data_access) to the footer of every page.
3. It includes the query service lag (compare [T221774](https://phabricator.wikimedia.org/T221774)) into the [max lag](https://www.mediawiki.org/wiki/Manual:Maxlag_parameter) calculation. (The other main contributor being [replication lag](https://www.mediawiki.org/wiki/Manual:Database_access#Lag).)
4. It holds the i18n messages for the Lexeme links in the sidebar, so that they can be translated on translatewiki.net. (The links themselves are defined on-wiki in [MediaWiki:sidebar](https://www.wikidata.org/wiki/MediaWiki:Sidebar).)

Issues and tasks are tracked on [Phabricator](https://phabricator.wikimedia.org/project/view/125/)!

Installation
------------

After cloning this extension into the extensions directory, add the following line to your LocalSettings.php:

```php
wfLoadExtension( 'Wikidata.org' );
```

Development
-----------

This extension follows a layout that is very similar to many other extensions.

Its PHPUnit tests are run as any other extension's PHPUnit tests (which is specific to your particular setup).

You can run PHP code style checks with

```bash
composer run test
```

and fix fixable violations with

```bash
composer run fix
```

Similarly, you can lint the JavaScript code with

```bash
npm test
```
