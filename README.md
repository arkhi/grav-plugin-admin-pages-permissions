# Admin Pages Permissions Plugin

The **Admin Pages Permissions** Plugin is an extension for [Grav CMS](http://github.com/getgrav/grav). Manage Interaction Permissions for Users with access rights to Pages in the Admin.

It allows to limit what a user can see or do when accessing pages, including:

- Adding specific permissions to groups or users.
- Filter the list of pages shown to the logged in user based on those permissions.
- Lock a custom list of page’s properties.

Permissions can be assigned for specific groups or users, globally or per page.

## Installation

You can installl this plugin by following any of the options available with the _NAME_ `admin-pages-permissions`:

- The **GPM** (Grav Package Manager) with `bin/gpm install admin-pages-permissions`.
- The **manual** method by unzipping the content of the [zip file](https://github.com/arkhi/grav-plugin-admin-pages-permissions/archive/master.zip) into `user/plugins/admin-pages-permissions`.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/admin-pages-permissions/admin-pages-permissions.yaml` to `user/config/plugins/admin-pages-permissions.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
permissions:
  groups:
    supervisors:
      create: true
      read: true
      update: true
      delete: true
      move: true
```

- `permissions` defines what a specific user can do with when editing a page, depending on the group they belong to and the settings applied on each page.
- `groups` is a list of groups.
- `users` is a list of accounts’ usernames.
- `create` allows to create new pages in nodes the user has access to, and below.
- `read` filters pages the user can update or access. Its is possible to access via the URL, but the edition will only be possible if the user has accesses.
- `update` allows to update a page.
- `delete` allows to delete a page and all its descendants.
- `move` allows to move a page to another node.

A full example would be as follow:

```yaml
permissions:
  groups:
    editors:
      create: true
      read:   true
      update: true
      delete: false
      move:   false
    others:
      delete: false
      move:   false
  users:
    john:
      create: false
      delete: false
      move:   false
    jane:
      delete: true
```

This can appear in `user/config/plugins/admin-pages-permissions.yaml` to setup defaults, or on any page to have a more fine‑grained approach.

Note that if you use the Admin Plugin, a file with your configuration named admin-pages-permissions.yaml will be saved in the `user/config/plugins/`-folder once the configuration is saved in the Admin.

## Usage



## Credits

Kuddos to Grav users for the support on Discord. :)

## To Do

- [ ] Clean up.
- [ ] Add Tests.

