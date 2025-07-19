# Translation Files for WP Employee Leaves Plugin

This directory contains the internationalization (i18n) files for the WP Employee Leaves plugin.

## Files Structure

- `wp-employee-leaves.pot` - Template file containing all translatable strings
- `wp-employee-leaves-es_ES.po` - Spanish (Spain) translation source file
- `wp-employee-leaves-es_ES.mo` - Spanish (Spain) compiled translation file
- `wp-employee-leaves-fr_FR.po` - French (France) translation source file
- `wp-employee-leaves-fr_FR.mo` - French (France) compiled translation file

## Adding New Translations

To add a new language:

1. Copy the `wp-employee-leaves.pot` file to `wp-employee-leaves-[locale].po`
2. Translate all the `msgstr` entries in the new .po file
3. Compile the .po file to .mo using: `msgfmt wp-employee-leaves-[locale].po -o wp-employee-leaves-[locale].mo`

## Updating Existing Translations

When the plugin is updated with new strings:

1. Update the .pot file with new strings
2. Merge new strings into existing .po files using: `msgmerge -U wp-employee-leaves-[locale].po wp-employee-leaves.pot`
3. Translate any new strings
4. Recompile the .mo file

## Supported Languages

- Spanish (Spain) - es_ES
- French (France) - fr_FR

## Text Domain

The plugin uses the text domain `wp-employee-leaves` for all translatable strings.

## Tools Required

- `xgettext` - For extracting translatable strings
- `msgfmt` - For compiling .po files to .mo files
- `msgmerge` - For updating .po files with new strings

## WordPress Integration

WordPress automatically loads the appropriate language files based on the site's language settings. The plugin registers the text domain using `load_plugin_textdomain()` in the main plugin file.