# DokuWiki to Markdown Converter

## Introduction

Tools to convert the DokuWiki syntax to Markdown syntax.
Please note it has some specific Liquibase regular expressions in `scripts/DocuwikiToMarkdownExtra.php`.

## TODOS

### Fix the following conversion inconsistencies

    <del> is replaced by `<del>`. It should re replaced by <strike> - without the `` wrap
    \\ should be replaced by 4 spaces and a line break
    Links should be encapsulated by <>
    bullets (like * or -) should have no spaces in front of them, and only one after
    rowspans (:::) in tables are not supported
    colspans aren't supported either, due to limitations in Markdown syntax - instead, empty cells will be inserted

## Convert single files

Place a file in the input dir, and run:

    php convert.php input/myfile.txt

This will convert your file, and place a `myfile.md` next to your file.

## Advanced usage (not yet tested)

### Import an entire wiki

    scp -P 2222 -r <username>@myserver:/sites/mysite/www/assets/data/pages/* input/

### Convert to Markdown files

    cd scripts
    php Translate.php ../input <../output> < template.txt>

`../output` - if output is not supplied it will print the conversion to `stdout`.

`template.txt` - at the moment it only prepends the content of the template to each file it converts.

### Images

The script will generate Markdown code for images. Notes:

- As Markdown doesn't support resizing, the images are always shown full size!
- The script doesn't copy image files; it just creates image links. It is up to the user to copy the images from DokuWikis
`data/media` directory to the directory containing the Markdown file(s). Eg.:

```bash
cp -rp /srv/www/wiki/htdocs/data/media/. ../output/
```


## Credit

- This project was forked from the [SilverStripe Documentation Restructuring Project](https://github.com/chillu/silverstripe-doc-restructuring)
and was edited to add some extra functionality by [ludoza](https://github.com/ludoza).
- It has subsequently been forked from [ludoza's fork](https://github.com/ludoza/Liquibase-DokuWiki-to-Markdown-Converter) by [titledk](https://github.com/titledk) with the
aim of creating a common way to export dokuwiki files to markdown (which is what we need in our 2013 restructuring here
at title.dk).
- [Alexander Skwar](https://github.com/alexs77) forked it from [Martin de la Iglesia's fork](https://github.com/Martin-de-la-Iglesia/DokuWiki-to-Markdown-Converter)
and added support for image links in 2017. Indented code blocks are now
working again as well.
