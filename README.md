MediaWiki Genealogy extension
=============================

All details: https://mediawiki.org/wiki/Extension:Genealogy


## Usage summary

This extension creates one parser function: `{{#genealogy: … }}`.
Its first first parameter is unnamed (i.e. doesn't have an equals sign) but all others are.

The following parameters are supported, two for defining data and four for reporting data:

2. Define and output a link to a parent:<br />
   `{{#genealogy:parent | Page Name Here }}`
3. Define a partner (no output produced; use `partners` to list):<br />
   `{{#genealogy:partner | Page Name Here |start date=Y-m-d |end date=Y-m-d }}`
4. List all siblings:<br />
   `{{#genealogy:siblings}}`
5. List all partners:<br />
   `{{#genealogy:partners}}`
6. List all children:<br />
   `{{#genealogy:children}}`
7. Display a tree (a connected graph):<br />
   `{{#genealogy:tree|ancestors=List|descendants=List}}`<br />
   where each `List` is a newline-separated list of page titles.


## Templates

**Example:**
For an example template that makes use of these parser functions, see `person_template.wikitext`.

**Preload:**
When this extension creates a link to a page that doesn't yet exist,
the text of `[[Template:Person/preload]]` is preloaded.
The location of this preload text can be customised
by modifying the `genealogy-person-preload` system message.

**Person list-item:**
Three types of lists of people can be generated: `siblings`, `partners`, and `children`.
The default behaviour is a simple bulleted list,
but this can be overridden by a template, `Template:Person/list-item`
(the template name is specified by the `genealogy-person-list-item` system message).
For example, to get a comma-separated one-line list of people, the following template code could be used:

```
{{{link}}}{{#ifeq:{{{index}}}|{{{count}}}|.|,}}
```

There are four parameters that are available for use in the list-item template:
* `link` — A wikitext link.
* `title` — The page title.
* `index` — The index of this list-item in the full list, starting from 1. 
* `count` — The total number of items in the full list.


## Installation

1. Clone the *Genealogy* and *GraphViz* extensions into your extensions directory:
   ```
   $ cd extensions
   $ git clone https://gerrit.wikimedia.org/r/p/mediawiki/extensions/GraphViz.git
   $ git clone https://gerrit.wikimedia.org/r/p/mediawiki/extensions/ImageMap.git
   $ git clone https://gerrit.wikimedia.org/r/p/mediawiki/extensions/Genealogy.git
   ```
2. Enable them in your `LocalSettings.php` file:
   ```
   require_once "$IP/extensions/GraphViz/GraphViz.php";
   require_once "$IP/extensions/ImageMap/ImageMap.php";
   wfLoadExtension( 'Genealogy' );
   ```


## Development

The *Genealogy* extension is developed by Sam Wilson and released under version
3 of the GPL (see `LICENSE.txt` for details).

You can see this extension in use on [ArchivesWiki](https://archives.org.au).

Please report all bugs via Phabricator: http://phabricator.wikimedia.org/
