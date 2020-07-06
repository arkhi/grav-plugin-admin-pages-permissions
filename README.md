# Admin Pages Permissions Plugin

**This README.md file should be modified to describe the features, installation, configuration, and general usage of the plugin.**

The **Admin Pages Permissions** Plugin is an extension for [Grav CMS](http://github.com/getgrav/grav). Manage Interaction Permissions for Users with access rights to Pages in the Admin.

## Installation

Installing the Admin Pages Permissions plugin can be done in one of three ways: The GPM (Grav Package Manager) installation method lets you quickly install the plugin with a simple terminal command, the manual method lets you do so via a zip file, and the admin method lets you do so via the Admin Plugin.

### GPM Installation (Preferred)

To install the plugin via the [GPM](http://learn.getgrav.org/advanced/grav-gpm), through your system's terminal (also called the command line), navigate to the root of your Grav-installation, and enter:

    bin/gpm install admin-pages-permissions

This will install the Admin Pages Permissions plugin into your `/user/plugins`-directory within Grav. Its files can be found under `/your/site/grav/user/plugins/admin-pages-permissions`.

### Manual Installation

To install the plugin manually, download the zip-version of this repository and unzip it under `/your/site/grav/user/plugins`. Then rename the folder to `admin-pages-permissions`. You can find these files on [GitHub](https://github.com/arkhi/grav-plugin-admin-pages-permissions) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/admin-pages-permissions

> NOTE: This plugin is a modular component for Grav which may require other plugins to operate, please see its [blueprints.yaml-file on GitHub](https://github.com/arkhi/grav-plugin-admin-pages-permissions/blob/master/blueprints.yaml).

### Admin Plugin

If you use the Admin Plugin, you can install the plugin directly by browsing the `Plugins`-menu and clicking on the `Add` button.

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

You can add any or all of the permissions for specific groups or users.

The pages will be displayed based on the rights the user has for each node of the pages’s tree.

## Credits

Kuddos to Grav users for the support on Discord. :)

## To Do

- [ ] Clean up.
- [ ] Add Tests.

