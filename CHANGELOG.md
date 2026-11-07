# Changelog

All notable changes to this module are documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the module follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-11-07

### Added
- The tag list, the cron editor and the code editor are drawn with the shared admin UI kit
  (`hryvinskyi/magento2-admin-ui`, a new dependency): its inputs, error text, typeface and colour tokens. Each
  widget's root carries the kit's `hui` class, so the kit reaches only the markup this module prints; the
  configuration page, its fieldsets, the "Use system value" checkbox, Magento's buttons and the category selector
  keep the admin theme.
- `data-role` hooks on every part of the three widgets, `data-disabled="true"` on a read-only widget's root, and
  `aria-invalid="true"` on an invalid entry.
- Tag-list storage formats are a pool of codecs keyed by save type (`Api\TagListCodecPoolInterface`,
  `Api\TagListCodecInterface`), shared by the backend model and the field. Another module adds a format by adding an
  item to the pool's `codecs` argument in `di.xml`. A codec's `decode()` returns `null` for a stored value it cannot
  read, which the field keeps as it is, and an empty list for an empty value or an empty list in its format.
- The tag list's wording (the remove button, the duplicate message, the default invalid-value message, the search
  input) and the cron editor's wording are translatable.
- `i18n/en_US.csv`, the dictionary of every phrase the module translates.
- The category selector is drawn like the other widgets: a template that escapes everything it prints, its settings
  passed to its script (`js/ui-select-field`) as JSON with `<` and `>` escaped, `data-role` hooks
  (`hcf-ui-select`, `hcf-ui-select-control`, `hcf-ui-select-value`, `hcf-ui-select-unsupported`) and
  `data-disabled="true"` on a read-only field's root. Its root carries the kit's `hui-tokens` class, so Magento's
  select component inside keeps the admin theme.
- `Api\CategoryOptionSourceInterface`, the categories a category selector offers: one flat row per category with its
  parent, labelled in a given store view. The admin area binds it to a cached source over a catalog reader; another
  module can bind its own.
- The UI form element (`js/form/element/codemirror`) is drawn on the same hooks as the configuration field's code
  editor: a root `hui-tokens hcf-code` with `data-role="hcf-code"`, and `data-disabled="true"` while the element's
  `disabled` observable is set. The root carries the kit's tokens alone, because the form around it is Magento's
  markup. Its colours come from `css/codemirror-editor.css`, which a page links by asking for the layout handle
  `hryvinskyi_configuration_fields_code_editor` (the kit's stylesheet first, then the editor's); the configuration page
  uses the same handle.
- `js/codemirror-setup` (the modules, stylesheets and options an editor in a given mode and theme needs) and
  `js/stylesheet-link` (adds a stylesheet to the head once), shared by the configuration field's script and the UI
  form element, which each carried their own copy of the mode table.
- The cron editor's summary is written in the language of the admin user's interface. The view model passes the locale
  Magento's locale resolver reports (`de_DE`) in the script's config; `js/cron-locale` chooses the bundle's locale for
  it (the language and region as the bundle spells them, then the language alone, then English). The vendored
  cronstrue is now its 2.61.0 build with every locale, about 170 KB where the English-only build was about 21 KB
  (loaded on pages with a cron field only), under its MIT licence notice.
- A new translatable phrase, "This schedule cannot be described in words.", in the cron editor's wording
  (`undescribable`) and in `i18n/en_US.csv`.
- `Model\Config\Backend\CronExpression`, a backend model a cron field opts in to with `<backend_model>`. It checks the
  posted expression with the cron editor's rule on every save, the command line included, and refuses one Magento's
  scheduler cannot run with a translated message naming the field (its label, or its path when the save carries
  none) and the value; the refusal stops the save of the whole section. An empty value is saved, a valid one is
  stored as posted. The editor and the backend model accept a number or a step of at most two digits besides leading
  zeros: no part of a schedule counts past 59, and a number past an integer's range makes Magento's scheduler fail on
  every run.
- `phpcs.xml.dist`: the module's coding-standard ruleset (Magento2 without the vendored CodeMirror sources,
  `foldgutter.css` and the cronstrue bundle).

### Changed
- **Behaviour change for stored values:** the tag list's backend model checks every value against the field's
  `validation` pattern on save, from the admin and from `bin/magento config:set`, where earlier versions checked it in
  the browser only. A value the pattern refuses refuses the save of its whole section, with a message naming the
  field, the value and the field's `validation_message`; a value stored before this version that does not match its
  field's pattern therefore blocks its section until it is fixed or removed. A pattern PCRE cannot compile refuses
  every save of its field with a message naming it. The pattern, `uppercase` and `validation_message` are the
  `tagListConfig` argument declared for the field's frontend model (the tag-list block or a virtual type of it), so
  they are declared once, on the block, and a backend virtual type shared by several fields checks each by its own
  pattern; the backend model's own `tagListConfig` gives them only for a field whose frontend model is not a tag list.
  The check is the same wherever the value is saved (the admin, the storefront, a cron job, the web API, a setup patch,
  `bin/magento config:set`): the field is looked up in the admin's configuration structure, and the argument is read
  from the global and admin-area `di.xml` declarations, once per process, without applying them to the running
  container. Only the declared argument counts; a block class that changes its settings in code is not consulted.
  The JavaScript pattern is translated to PCRE so the server decides as the browser does (see the README): among
  others, `\s` and `\S` match JavaScript's Unicode whitespace inside a character class too, and a `-` beside a class
  escape such as `\d` is a hyphen. With `uppercase`, a value is upper-cased for the match and stored as posted.
- A valid cron expression that cannot be put into words (a minute of 61, which Magento accepts and never matches) shows
  the new neutral wording in the normal tone instead of "Invalid cron expression." in the error tone: the field saves,
  so it is not shown as an error.
- Every value the templates print is escaped: attributes double-encode, so a stored `&lt;` survives a save, and each
  widget's settings reach its script as JSON with `<` and `>` escaped, so a stored `</script>` is shown as text.
  The code editor's textarea keeps a stored entity such as `&lt;` as typed.
- A field is read-only when its value is inherited or locked in `config.php`/`env.php`, the state Magento itself sets
  on the element; earlier versions disabled every field that could inherit, whether it did or not.
- The tag list's hidden input posts the decoded values joined by the separator. A stored value the field's format
  cannot read (values joined by a newline in a `json` field, as a `config.xml` default or `config:set` may write them,
  a corrupted value, or a list none of whose entries is a value) is kept unchanged in the hidden input, with a notice
  in the widget and a warning in the log naming the field's path and save type; the next save of the section
  re-encodes it as values split by the separator, and adding or removing a value replaces it with the list shown. An
  empty list in the field's format (`[]`, `a:0:{}`) is an empty field: no notice, no warning, and the next save
  stores an empty value.
- A stored value that is not valid UTF-8 no longer leaves the configuration section empty: the three widgets show it
  with its malformed bytes replaced. An invalid byte is shown and, on the next save of the section, stored as the
  replacement character (mbstring's substitute character, `?` by default). The tag list's backend model refuses a
  posted value that is not valid UTF-8 in every save type, where only `json` refused it before.
- The cron editor writes nothing on load: an empty field stays empty when the section is saved, where earlier versions
  stored `* * * * *` and ran the job every minute.
- A cron field that held an expression can no longer be saved empty by clearing its parts; the form refuses the save
  and the field keeps its expression. A field that was empty may stay empty. Several cron fields on one page are
  validated independently.
- The cron editor's hidden input carries the field's own classes beside its validation rule, so a `<validate>` such
  as `required-entry` applies to the expression it posts, and its message shows under the field. The cron rule's own
  message is the summary above the parts; the label it leaves under the field is empty and hidden.
- The cron editor accepts what Magento's cron scheduler runs: month and weekday names (`jan`, `mon-fri`, `january`),
  steps on ranges and numbers (`1-30/5`, `5/15`), and numbers outside the calendar, which Magento accepts and never
  matches. A stored `0 2 * * mon` no longer blocks saving the section. Numbers are written in digits. A stored
  six-part expression, which Magento accepts and whose sixth part it never reads, is accepted; the editor shows and
  edits its five parts and keeps the sixth.
- The cron editor refuses a step of zero (`*/0`, `1-5/0`), which Magento's scheduler fails on every time it runs, and
  separates parts only by the whitespace Magento's scheduler splits on (spaces, tabs, line breaks): a stored expression
  with a no-break space between or around its parts is invalid, as the scheduler fails on it.
- The default message for a tag value that does not match the default pattern names the characters the pattern
  accepts: "Use letters, digits and . ! # $ % & ' * + / = ? ^ _ ` " { | } ~ - only, without spaces." A field with a
  `validation` pattern of its own and no `validation_message` shows "This value does not match the format this field
  accepts." instead.
- Stored serialized values are read with classes disallowed; no stored object is ever instantiated.
- The category selector labels the categories in the store view the configuration page edits: the store view at
  store-view scope, the website's default store view at website scope, the admin names at default scope. Earlier
  versions always showed the admin names.
- The category selector keeps its categories in Magento's cache under the module's own id,
  `HRYVINSKYI_CONFIGURATION_FIELDS_CATEGORY_OPTIONS_<store>[_<role>]`, with the same tags as before (the category tag
  and the block cache tag). Earlier versions wrote under the id prefix of Magento's product form category tree.
- The category selector reads the catalog in one query instead of two, with sibling categories in their catalog
  position.
- A category selector on a `select` field shows its stored category as picked; earlier versions handed the stored id
  over as a list, which a single select never matches.
- A category selector on a field of another type shows a translated notice naming the type, escaped; earlier versions
  printed the type unescaped, and still loaded the category tree.
- The category selector wraps the field's before- and after-element HTML once, with the label wrapper the other
  widgets use. Its read-only look comes from `css/ui-select.css`, linked by the configuration page, instead of a style
  block and a script printed with every field. The stored ids reach Magento's select component after it is created,
  so a stored value is never rendered as a component template.
- `"0"` is kept as a value instead of being dropped as empty.
- `Magento_Catalog` and `Magento_Ui`, which the category selector uses, are declared dependencies.
- The three stylesheets are plain CSS that read the kit's tokens; the module's own palette is gone. The code editor's
  frame reaches only editors inside the field's root, and its colours apply to CodeMirror's default theme only, so a
  field that names a dark theme keeps that theme's palette. The configuration page links the kit's stylesheet before
  the module's.
- A UI form's code editor destroyed while its modules are still loading opens no editor, and an editor that is
  destroyed stops following the element's `disabled` state.
- `Model\Field\AddonLabelWrapper::wrap()` is `@api`: every widget field renders through it, so a module that adds
  markup after widget fields plugs this method.

### Upgrade notes
- Enable `Hryvinskyi_AdminUi` and run `bin/magento setup:upgrade`: the kit is now in this module's sequence.
- The tag-list backend model's constructor takes the codec pool, the settings reader, the field settings source,
  the value check and the field name before `$tagListConfig`. A subclass that calls the parent constructor
  positionally must pass them in that order; virtual types configured with named `di.xml` arguments need no change.
- Before upgrading, check the stored values of every tag-list field against its pattern: a value that does not match
  now stops the save of its section.
- A `save_type` no storage format is registered under now refuses the save with a message naming it. The field still
  renders: it reads the stored value as values joined by the separator and logs the save type with the field's path.
- The inner classes of 1.0.x (`tag-list-container`, `tag-list-tag`, `tag-list-input`, `tag-list-disabled`,
  `cron-editor-*`, `cron-syntax-table`, `codemirror-disabled`, the `invalid` state class and the ids built from the
  field id) are gone. The root classes `tag-list-root`, `cron-editor-root` and the configuration field's
  `codemirror-editor-root` stay; a read-only widget carries `data-disabled="true"` on its root, and a rejected value
  `aria-invalid="true"`.
- `css/tag-list.less`, `css/cron-editor.less` and `css/codemirror-editor.less` are now `.css` files under the same
  asset ids; a theme that overrides one of the `.less` files must override the `.css` file instead. After upgrading,
  remove the published copies under `pub/static/adminhtml/*/*/*/Hryvinskyi_ConfigurationFields` and
  `var/view_preprocessed/pub/static/adminhtml`, or the stylesheets compiled from the old files are still served.
- A field block subclass that declares its own constructor must forward `$data` to its parent: the view model and the
  label wrapper arrive as items of that argument, and a block without them stops with an error naming the item.
- `Block\...\Field\UiSelect` extends the widget base like the other fields: its constructor takes only the context and
  `$data`, and `getUiComponentInitializationScript()` and `getCategoriesTree()` are gone. A subclass that passed the
  old collaborators must drop them; a theme or script that hooked the `ui-select-inherited` class reads
  `data-disabled="true"` on the `hcf-ui-select` root instead. Entries written under the old cache id are no longer read
  and go with the next cache clean.
- The UI form element's root is `hui-tokens hcf-code` instead of `codemirror-editor-root`, and the `codemirror-disabled`
  state class is gone: a read-only element carries `data-disabled="true"` on the root. A theme or script that hooked
  those classes reads the `hcf-code` root and its `data-disabled` attribute instead. A page that holds the element
  asks for the `hryvinskyi_configuration_fields_code_editor` layout handle to get the editor's colours: earlier
  versions linked the editor's stylesheet on the configuration page only.

## [1.0.0] – [1.0.11]

The cron editor, the category selector, the tag list with JSON, serialized and separated storage, and the CodeMirror
code editors for system configuration and UI forms.
