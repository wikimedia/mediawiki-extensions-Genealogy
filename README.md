# MediaWiki Genealogy extension

## Usage

There is only one parser function, `{{#genealogy:}}`.
Its first two parameters are unnamed (i.e. don't have equals signs), but all
others must be (dates, etc.).

The following functions are supported, three for defining data and three for
reporting data:

1. Define this person's dates.
  `{{#genealogy:person |birth date=Y-m-d |death date=Y-m-d }}`
2. Define a parent:
   `{{#genealogy:parent | Page Name Here }}`
3. Define a partner (no output produced; use `partners` to list):
   `{{#genealogy:partner | Page Name Here |start date=Y-m-d |end date=Y-m-d }}`
4. List all siblings:
   `{{#genealogy:siblings}}`
5. List all partners:
   `{{#genealogy:partners}}`
6. List all children:
   `{{#genealogy:children}}`

## Development

The *Genealogy* extension is developed by Sam Wilson and released under version
3 of the GPL (see `LICENSE.txt` for details).

Please report all bugs via the GitHub issue tracker at
https://github.com/samwilson/Genealogy/issues
