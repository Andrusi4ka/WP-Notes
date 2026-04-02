# WP Notes

WP Notes is a WordPress admin plugin for creating internal notes directly inside the WordPress dashboard.

It is designed for teams that need context-aware notes on admin pages, editorial reminders, process instructions, or technical comments that stay inside the admin interface.

## Screenshot Placeholders

Insert screenshots in the sections below as needed.

### Info Page

![screen](./assets/img/screens/screen-5.png)

### Note Display In Admin
![screen](./assets/img/screens/screen-3.png)
![screen](./assets/img/screens/screen-4.png)

### Note Editor Modal

![screen](./assets/img/screens/screen-1.png)

### All Notes Page

![screen](./assets/img/screens/screen-6.png)

## Main Capabilities

- Create a note for the current admin page.
- Create a global note for the whole site.
- Show notes directly inside the WordPress admin interface.
- Manage notes from a dedicated admin page.
- Edit notes in a modal window or on a dedicated admin screen.
- Write rich content with formatting, lists, code blocks, links, and images.
- Upload images directly into notes.
- Control editing permissions per note.
- Follow the WordPress admin/site language for plugin UI text.

## How The Plugin Works

WP Notes adds an admin menu and an admin bar entry inside WordPress.

From the admin bar, an authorized user can create:

- a page-specific note bound to the current admin screen
- a global note visible across the admin area

When a note exists, it is rendered near the top of the admin page as a collapsible card.

Users with permission can:

- open the note
- edit the note
- delete the note

There is also an "All Notes" page where stored notes can be reviewed and managed in one place.

## Data Storage

The plugin stores data in two places:

1. WordPress database
2. Plugin storage directory for uploaded note images

### Database Table

The plugin creates a dedicated custom table on activation:

- `{$wpdb->prefix}wp_notes`

The table stores note records such as:

- note scope
- internal uniqueness key
- screen ID
- page URL
- page title
- note content
- author ID
- edit mode
- created timestamp
- updated timestamp

### Does The Plugin Create New Tables?

Yes.

On activation, the plugin creates the custom table:

- `wp_notes` with the active WordPress table prefix

Example:

- `wp_wp_notes`
- `customprefix_wp_notes`

### Uniqueness Rules

The plugin enforces one stored note per logical target:

- one global note
- one note per screen target

This is enforced both in plugin logic and with a unique key in the database schema.

## Where Data Is Stored

### Note Content

Note content is stored in the custom database table as sanitized HTML.

### Uploaded Images

Uploaded note images are stored inside the plugin directory:

- `storage/uploads/`

Example path:

- `wp-content/plugins/WP-Notes/storage/uploads/filename.png`

The plugin also creates protective `index.php` files in:

- `storage/`
- `storage/uploads/`

## Image Handling Logic

### When Images Are Uploaded

When a user uploads an image inside the note editor:

- the file is validated as an image
- it is copied into `storage/uploads/`
- the generated image URL is inserted into the note content

### When Images Are Deleted

Images can be deleted automatically in two cases.

#### 1. When a note is edited

If an image existed in the old version of the note, but was removed from the updated content:

- the plugin checks whether that same image is still referenced by other notes
- if no other note uses it, the file is deleted immediately from `storage/uploads/`

#### 2. When a note is deleted

When a note is deleted entirely:

- the plugin scans the note content for locally stored note images
- if an image is not referenced by any other note, the file is deleted from `storage/uploads/`

### What Is Not Deleted

An uploaded image is not deleted if:

- it is still present in the saved version of the note
- it is referenced by another note

## Permissions Model

The plugin currently uses WordPress capabilities and per-note edit mode.

### Access Summary

- Users with `edit_pages` can access and use the plugin.
- Users with `manage_options` can always edit notes.
- A note can be configured as:
  - `author`: only the original author can edit it, unless the user has `manage_options`
  - `anyone`: any user with plugin access can edit it

### Visibility

Notes are intended for WordPress admin users with the required capability and are rendered only in the admin area.

## Admin Pages And UI

The plugin provides:

- admin bar shortcuts for creating notes
- an "All Notes" admin page
- an "Info" admin page
- modal-based note editing
- a dedicated add/edit note screen fallback

## Rich Text Editor

The note editor supports:

- headings
- bold, italic, underline, strike
- ordered and unordered lists
- blockquotes
- code blocks
- links
- text color and background color
- font selection
- font size options
- image upload
- image resize
- image alignment tools
- undo and redo

## Third-Party Libraries

The plugin bundles the following frontend libraries:

### Quill

Used for rich text editing.

Files:

- `assets/vendor/quill/quill.min.js`
- `assets/vendor/quill/quill.snow.css`

### quill-image-resize

Used to resize images inside the Quill editor.

Files:

- `assets/vendor/quill-image-resize/image-resize.min.js`

### highlight.js

Used to highlight code blocks in rendered notes.

Files:

- `assets/vendor/highlightjs/highlight.min.js`
- `assets/vendor/highlightjs/github.min.css`

## Sanitization And Content Safety

The plugin sanitizes saved note content before storing it.

Allowed content includes common post-like HTML such as:

- paragraphs
- headings
- blockquotes
- code blocks
- links
- spans
- ordered and unordered lists
- list items
- images

Only a limited set of inline CSS properties is allowed.

## Language Support

The plugin currently includes:

- English
- Norwegian

The plugin follows the active WordPress locale automatically.

Current locale mapping:

- `en*` -> English
- `no*`, `nb*`, `nn*` -> Norwegian
- unsupported locales -> English fallback

## Activation

On activation, the plugin:

- creates the custom notes table
- ensures the storage directories exist
- creates `index.php` protection files inside storage folders

## Uninstall Behavior

When the plugin is uninstalled through WordPress, it performs cleanup.

### What Is Removed

- the custom database table
- the entire `storage/` directory created by the plugin, including uploaded note images

### What This Means

Uninstalling the plugin removes:

- all saved notes
- all uploaded note images used by the plugin

This cleanup is destructive by design.

## Files And Structure

Main files and directories:

- `wp-notes.php` - plugin bootstrap file
- `includes/` - core PHP classes
- `assets/` - CSS, JavaScript, images, and bundled libraries
- `storage/uploads/` - uploaded note images
- `uninstall.php` - uninstall cleanup logic

## Current Technical Notes

- The plugin works only in the WordPress admin area.
- Notes are stored as HTML, not as Gutenberg blocks.
- Uploaded images are stored inside the plugin directory, not inside the normal WordPress uploads folder.
- The plugin includes its own image cleanup logic for note edits and note deletions.

## Suggested Screenshot Placement

Use these headings in the final published README if you want a polished presentation:

1. Overview screenshot under "Main Capabilities"
2. Info page screenshot under "Language Support"
3. Editor screenshot under "Rich Text Editor"
4. Admin note card screenshot under "How The Plugin Works"
5. All notes list screenshot under "Admin Pages And UI"
