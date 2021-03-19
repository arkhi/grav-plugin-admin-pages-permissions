# Admin Pages Permissions Plugin

The **Admin Pages Permissions** Plugin is an extension for [Grav](http://github.com/getgrav/grav) 1.6. Manage Interaction Permissions for Users with access rights to Pages in the Admin.

It allows to limit what a user can see or do when accessing pages, including:

- Adding specific permissions to groups or users.
- Filter the list of pages shown to the logged in user based on those permissions.
- Lock a custom list of page’s properties.
- Show or hide elements on the interface based on those permissions.

Permissions can be assigned for specific groups or users, globally or per page.

## Installation

You can installl this plugin by following any of the options available with the _NAME_ `admin-pages-permissions`:

- The **GPM** (Grav Package Manager) with `bin/gpm install admin-pages-permissions`.
- The **manual** method by unzipping the content of the [zip file](https://github.com/arkhi/grav-plugin-admin-pages-permissions/archive/master.zip) into `user/plugins/admin-pages-permissions`.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/admin-pages-permissions/admin-pages-permissions.yaml` to `user/config/plugins/admin-pages-permissions.yaml` and only edit that copy.

Here is the default configuration:

```yaml
enabled: true

locked_props: null

locked_header:
  - author
  - permissions

```

### Permissions

A `pages_super` access permissions can be setup for super users. This can go in the user configuration, groups or accounts.

An `access.admin.pages_permissions` array can define default permissions for all pages (the latest prevails):

- plugin
- user
- environment
- groups
- user


```yaml
access:
  admin:
    pages_permissions:
      groups:
        editors:
          create: true
          read:   true
          update: true
          delete: true
          move:   false
        others:
          delete: false
          move:   true
      users:
        john:
          create: false
          delete: false
          move:   false
        jane:
      delete: true
```

- `pages_permissions` defines what a specific user can do with when editing a page, depending on the group they belong to and the settings applied on each page.
- `groups` is a list of groups.
- `users` is a list of accounts’ usernames.
- `create` allows to create new pages in nodes the user has access to, and below.
- `read` filters pages the user can update or access. Its is possible to access via the URL, but the edition will only be possible if the user has accesses.
- `update` allows to update a page.
- `delete` allows to delete a page and all its descendants.
- `move` allows to move a page to another node.

---

- When a user is a member of multiple groups, `true` prevails.
- The permissions for groups are overridden by permissions for users.

Let’s consider a page at the root with this example; for the current page these should be the resulting permissions:

- Any member of the group `editors`:       create, read, update, delete;
- Any member of the group `others`:        move;
- A member of both `editors` and `others`: all permissions;
- The user `john`:                         no permission;
- The user `john`, member of `editors`:    read, update;
- The user `jane`:                         delete;
- The user `jane`, member of `editors`:    create, read, update, delete;
- The user `jane`, member of `others`:     delete, move;

A child page would inherit those permissions and apply its own if they exist.

### Locked Properties

```yaml
locked_props:
  parent:
    dependencies:
      - path
      - route
  template:
    dependencies:
      - name
```

- `locked_props` are properties that will be reverted if the user tries to update them, along with their dependencies, unless the user is a _super user_ (supervisor).
- `parent` is the name of the property being updated when one decides to change the parent from the Admin.
- `dependencies` are page properties computed by Grav based on the updated property; they need to be ignored as well. In that example, `path` and `routes` will be reverted as well if `parent` is locked.

In this example, trying to update the parent or the template should show a warning for each locked property that was intended to be updated, along with an informative message saying the changes were not applied. **All other changes are applied**.

### Locked Header

```yaml
locked_header:
  - author
  - permissions
  - sitemap
```

`locked_header` are properties from the page frontmatter that will be reverted if the user tries to update them, unless the user is a _super user_ (supervisor).

## Usage

### Permissions

The permissions are merged from global to local context:

1. `user/plugins/admin-pages-permissions/admin-pages-permissions.yaml` for the defaults.
1. `user/config/plugins/admin-pages-permissions.yaml`
1. `user/ENVIRONMENT/config/plugins/admin-pages-permissions.yaml` where `ENVIRONMENT` depends on your [_Environment Configuration_](https://learn.getgrav.org/advanced/environment-config).
1. `access.admin.pages_permissions` in groups a user belongs to.
1. `access.admin.pages_permissions` in the account.
1. The frontmatter of the furthest ancestor of the current page.
1. The frontmatter of the closest ancestor of the current page.
1. The frontmatter of the current page.

**A previously set permission can not be unset, but can be changed to `true` or `false`.**

Note that the author of a page will receive all <abbr title="Create, Read, Update and Delete">CRUD</abbr> permissions.

### Locked properties

Values for _locked properties configuration_ are replaced by user defined values in the user config or environment files:

If `user/config/plugins/admin-pages-permissions.yaml` contains:

```yaml
locked_props:
  parent:
    dependencies:
      - path
      - route
  template:
    dependencies:
      - name
locked_header:
  - author
  - permissions
  - sitemap

```

and `user/ENVIRONMENT/config/plugins/admin-pages-permissions.yaml`:

```yaml
locked_props:
locked_header:
```

Then all page properties and header’s properties can be changed when a page is saved.

## Credits

Kuddos to Grav users for the support on [Discord](https://discord.gg/EeNpnz). :)

## To do

- [ ] Simplify storage of permissions.
- [ ] Add Functional tests.
- [ ] Add Unit tests.
