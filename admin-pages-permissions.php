<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Yaml;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Page;
use Grav\Common\Twig\Twig;
use Grav\Common\User\User;
use Grav\Framework\File\File;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class AdminPagesPermissionsPlugin
 * @package Grav\Plugin
 */
class AdminPagesPermissionsPlugin extends Plugin
{
    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100000], // @todo Remove when plugin requires Grav >=1.7
                ['onPluginsInitialized', 0],
            ],
        ];
    }

    /**
    * Composer autoload.
    *
    * @return ClassLoader
    */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Handle restrictions and initialization of the plugin as early as possible.
     *
     * @param  Event  $event
     *
     * @return null
     */
    public function onPluginsInitialized(Event $event)
    {
        // Stop if:
        // * We are not in the Admin plugin;
        // * User is not logged in.
        // =====================================================================
        if (
            !$this->isAdmin()
            || !$this->grav['user']->authenticated
        ) {
            return;
        }

        $this->enable([
            'onAdminTwigTemplatePaths' => [
                [ 'onAdminTwigTemplatePaths', 0 ],
            ],
            'onPageInitialized' => [
                [ 'onAdminPageInitialized', 0 ],
            ],
            'onAdminCreatePageFrontmatter' => [
                [ 'addAuthorToPage', 1 ],
                [ 'canCreate', 0 ],
            ],
            'onAdminSave' => [
                [ 'canUpdate', 0 ],
            ],
        ]);
    }

    /**
     * [onAdminCreatePageFrontmatter]
     * Add an author header when creating a page.
     *
     * @param Event $event
     */
    public function addAuthorToPage( Event $event )
    {
        $page   = $event;
        $header = $page['header'];

        if (!isset($header['author'])) {
            $header['author'] = $this->grav['admin']->user->get('username');
            $event['header']  = $header;
        }
    }

    /**
     * Check if the user can manage all pages without restriction.
     *
     * @param  User    $user Current logged in user
     *
     * @return boolean
     */
    public function isPagesSuper(User $user): bool
    {
        return $user->authorize('admin.pages_super') || $user->authorize('admin.super');
    }

    /**
     * Sort two paths from the deepest descendants to the oldest ancestors.
     * This function is used in to sort an array of paths in a sorting function.
     *
     * @param  string $path1 First path to compare
     * @param  string $path2 Second path to compare
     *
     * @return integer
     */
    private function deepToRoot($path1, $path2): int
    {
        $depth1 = count(explode('/', $path1));
        $depth2 = count(explode('/', $path2));

        return ($depth1 > $depth2) ? -1 : 1;
    }

    /**
     * Check that no locked property has been changed.
     * The locked properties will be reverted if necessary.
     *
     * @param  Page $original Original Page
     * @param  Page $new      Updated Page
     *
     * @return Page           Filtered Page
     */
    public function checkLockedProps(Page $original, Page $new): Page
    {
        $filtered    = $new;

        $lockedProps = $this->grav['config']["plugins.admin-pages-permissions"]['locked_props'];

        foreach ($lockedProps as $property => $value) {
            // If the property has been added, removed or changed…
            $isAdded =
                !method_exists($original, $property)
                && method_exists($filtered, $property);

            $isRemoved =
                method_exists($original, $property)
                && !method_exists($filtered, $property);

            $isUpdated =
                method_exists($original, $property)
                && method_exists($filtered, $property)
                && $original->$property() !== $filtered->$property();

            if ($isAdded) {
                unset($filtered->$property);
            } elseif ($isRemoved || $isUpdated) {
                $filtered->$property($original->$property());

                foreach ($value['dependencies'] as $dependency) {
                    $filtered->$dependency($original->$dependency());
                }
            } else {
                continue;
            }

            // Inform the user.
            $this->warnings++;

            $this->grav['messages']->add(
                $this->grav['language']->translate([
                    'PLUGIN_ADMIN_PAGES_PERMISSIONS.WARNING_PROPERTY_LOCKED',
                    $property
                ]),
                'warning'
            );
        }

        return $filtered;
    }

    /**
     * Check that no locked property has been changed in the header of a Page.
     * The locked headers will be reverted if necessary.
     *
     * @param  object $original Original header
     * @param  object $new      Updated header
     *
     * @return object           Filtered header
     */
    public function checkLockedHeaderProps(object $original, object $new): object
    {
        $filtered = $new;

        $lockedProps = $this->grav['config']["plugins.admin-pages-permissions"]['locked_header'];

        foreach ($lockedProps as $property) {
            // If the property has been added, removed or updated…
            $isAdded =
                !property_exists($original, $property)
                && property_exists($filtered, $property);

            $isRemoved =
                property_exists($original, $property)
                && !property_exists($filtered, $property);

            $isUpdated =
                property_exists($original, $property)
                && property_exists($filtered, $property)
                && $original->$property !== $filtered->$property;

            if ($isAdded) {
                unset($filtered->$property);
            } elseif ($isRemoved) {
                $filtered->$property = $original->$property;
            } elseif ($isUpdated) {
                $filtered->$property = $original->$property;
            } else {
                continue;
            }

            // Inform the user.
            $this->warnings++;

            $this->grav['messages']->add(
                $this->grav['language']->translate([
                    'PLUGIN_ADMIN_PAGES_PERMISSIONS.WARNING_PROPERTY_LOCKED',
                    $property
                ]),
                'warning'
            );
        }

        return $filtered;
    }

    /**
     * Get pages in the branch of the current page, up to the root.
     *
     * @param  Page        $page   Current page
     * @param  array       $branch Array of path:Page items
     *
     * @return (Page)array         Array of path:Page items
     */
    public function getBranchUp(Page $page, $branch = []): array
    {
        if (!$page) {
            return [];
        }

        $branch[$page->path()] = $page;

        $parent = $page->parent();

        if ($parent) {
            $branch = $this->getBranchUp($parent, $branch);
        }

        return $branch;
    }

    /**
     * Get pages in the branch of the current page, down to the tips.
     *
     * @param  Collection  $pages  Collection of pages (can be just one)
     * @param  array       $branch Array of path:Page items
     *
     * @return (Page)array         Array of path:Page items
     */
    public function getBranchDown(Collection $pages, $branch = []): array
    {
        foreach ($pages as $page) {
            if (!$page) {
                continue;
            }

            $branch[$page->path()] = $page;

            $children = $page->children();

            if (count($children) > 0) {
                $branch = $this->getBranchDown($children, $branch);
            }
        }

        return $branch;
    }

    /**
     * Define which nodes of the tree should be visible to the current user.
     * If a page is visible in a branch, then all nodes closer to the root will
     * be visible, but not necessarily updatable.
     *
     * @param  (array)[] $pathsPerms Array of path:permissions items
     *
     * @return (array)[]             Array of path:permissions items
     */
    public function getVisibleTreeForUser($pathsPerms): array
    {
        // Sort paths from furthest descendants to root.
        uksort($pathsPerms, [$this, 'deepToRoot']);

        // Climb up the tree to assign proper read permissions to parents.
        foreach ($pathsPerms as $path => $perms) {
            if (
                $perms['create'] === true
                || $perms['read'] === true
                || $perms['update'] === true
                || $perms['delete'] === true
            ) {
                $parent = substr($path, 0, strrpos($path, '/'));

                // While we’re still parsing paths within the pages…
                while (strstr($parent, 'pages/') !== false) {
                    $pathsPerms[$parent]['read'] = true;

                    $parent = substr($parent, 0, strrpos($parent, '/'));
                }
            }
        }

        return $pathsPerms;
    }

    /**
     * List permissions specific to this user, for all paths provided.
     *
     * @param  array     $pages      Array of path:Page items
     * @param  array     $pathsPerms Array of path:permissions items
     *
     * @return (array)[]             Array of path:permissions items
     *
     */
    public function getPathsPermsForUser($pages, $pathsPerms = []): array
    {
        $user = $this->grav['user'];

        foreach ($pages as $path => $page) {
            $pathsPerms[$path] = $this->getPermsForUser(
                $page,
                $user
            );
        }

        return $pathsPerms;
    }

    /**
     * Get permissions from the header of a specific page.
     *
     * @param  Page           $page The current page
     *
     * @return (array)[]|null       Array with permissions permissions
     */
    private function getPermsFromPage(Page $page): ?array
    {
        if (!$page) {
            return null;
        }

        $header = $page->header();

        // If the current user is the author, give it default permissions.
        if (
            $header
            && property_exists($header, 'author')
            && $header->author === $this->grav['user']['username']
        ) {
            return [
                'users' => [
                    "$header->author" => [
                        'create' => true,
                        'read'   => true,
                        'update' => true,
                        'delete' => true,
                    ]
                ]
            ];
        }

        if ($header && property_exists($header, 'permissions')) {
            return $header->permissions;
        }

        return null;
    }

    /**
     * Get permissions of a page depending on the permissions of its ancestors in the branch.
     *
     * @param  Page              $page Current page
     *
     * @return (array)array|null       Closest ancestor Page if it exists.
     */
    private function getPermsForPage(Page $page): ?array
    {
        if (!$page) {
            return null;
        }

        $node      = $page;
        $permsTree = [];
        $grav      = Grav::instance();
        $name      = 'admin-pages-permissions';

        // 1. Get permissions from the default configuration of the plugin.
        $pathPlugin   = $grav['locator']->findResource("plugins://{$name}/{$name}.yaml");
        $filePLugin   = new File($pathPlugin);
        $configPlugin = Yaml::parse($filePLugin->load())['permissions'];

        // 2. Get permissions from the user configuration for the plugin.
        $pathUser   = $grav['locator']->findResource("user://config/plugins/{$name}.yaml");
        $fileUser   = new File($pathUser);
        $configUser = Yaml::parse($fileUser->load())['permissions'];

        // 3. Get permissions from the user configuration for the plugin and the
        //    current environment.
        //    This can be equal to the User configuration  if no configuration
        //    file exists for the current environment.
        $configEnv = $grav['config']["plugins.{$name}"]['permissions'];

        // Merge all configs, replacing existing values with more prevalent ones,
        // but keeping previously defined keys that would not exist in a newer
        // config.
        $permissions = array_replace_recursive($configPlugin, $configUser, $configEnv);

        // 3. Get permissions from the tree of Pages.
        // Gather permissions for each ancestor, from closest to furthest.
        while (true) {
            if ($this->getPermsFromPage($node) !== null) {
                $permsTree[] = $this->getPermsFromPage($node);
            }

            // Move to previous ancestor.
            $parent = $node->parent();

            if ($parent !== null && $parent->parent() !== null) {
                $node = $parent;
            } else {
                break;
            }
        }

        // Sort permissions from less relevant (furthest) to most relevant (closest).
        rsort($permsTree);

        // Merge permissions
        foreach ($permsTree as $key => $pagePerms) {
            $permissions = array_replace_recursive($permissions, $pagePerms);
        }

        return $permissions;
    }

    /**
     * Get permissions for the user in the context of the page.
     *
     * @param  Page          $page Current page
     * @param  User          $user Current user
     *
     * @return (bool)[]|null       Permissions
     */
    public function getPermsForUser(Page $page, User $user): ?array
    {
        $perms     = $this->getPermsForPage($page);
        $permsUser = [
            'create' => false,
            'read'   => false,
            'update' => false,
            'delete' => false,
            'move'   => false,
        ];

        if ($this->isPagesSuper($user)) {
            $permsUser = [
                'create' => true,
                'read'   => true,
                'update' => true,
                'delete' => true,
                'move'   => true,
            ];
        } elseif (is_array($user->groups)) {
            // Merge any permissions from groups the user is a member of.
            foreach ($user->groups as $group) {
                // If the group exists in permissions groups.
                if(is_array($perms['groups']) && array_key_exists($group, $perms['groups'])) {
                    foreach ($perms['groups'][$group] as $task => $authorized) {
                        // A user should not be part of a group if that group
                        // provides him rights it should not have.
                        // => Create the property or use any value if it is not
                        // already set to `true`.
                        if (!isset($permsUser[$task]) || $permsUser[$task] !== true) {
                            $permsUser[$task] = $perms['groups'][$group][$task];
                        }
                    }
                }
            }
        }

        // Merge any permissions for the user.
        // The user permissions will always take precedence over the group (generic to specific).
        if (
            isset($perms['users'])
            && is_array($perms['users'])
            && array_key_exists($user->username, $perms['users'])
        ) {
            foreach ($perms['users'][$user->username] as $task => $authorized) {
                $permsUser = array_replace_recursive($permsUser, $perms['users'][$user->username]);
            }
        }

        return $permsUser;
    }

    /**
     * When Twig Templates Path for the Admin are processed…
     *
     * @param  Event $event
     */
    public function onAdminTwigTemplatePaths( Event $event )
    {
        // Load admin templates from the theme.
        $paths   = $event['paths'];
        $paths[] = __DIR__ . '/admin/themes/grav/templates';

        $event['paths'] = $paths;
    }

    /**
     * When a Page is initialized in the Admin…
     *
     * @param  Event $event
     */
    public function onAdminPageInitialized( Event $event )
    {
        $user       = $this->grav['user'];
        $pathsPerms = null;

        $this->grav['twig']->twig_vars['pages_super'] = $this->isPagesSuper($user);

        // Stop if we’re not dealing with specific Admin locations.
        // “Dashboard” and “pages” locations let user interact with pages.
        // =====================================================================
        if (!in_array(
            $this->grav['admin']->location,
            ['dashboard', 'pages']
        )) {
            return;
        }

        // A route will not have a page when creating a new page; get the
        // closest existing page to the current route.
        $route = '/' . $this->grav['admin']->route;

        while (true) {
            $page  = $this->grav['page']->find($route);
            $route = substr($route, 0, strripos($route, '/') + 1);

            if ($page !== null || $route === '') {
                break;
            }
        }

        // Stop if we’re not dealing with a page.
        // =====================================================================
        if (!$page instanceof Page) {
            return;
        }

        // List permissions of current logged in user for each page provided.
        // =====================================================================
        if ($this->grav['admin']->route === null) {
            // If Listing pages (dashboard or list of all pages)…
            $pages = $this->grav['page']->evaluate(['@root.children']);

            $pathsPerms = $this->getVisibleTreeForUser(
                $this->getPathsPermsForUser(
                    $this->getBranchDown($pages)
                )
            );
        } else {
            // If editing a page…
            $pages = $this->grav['page']->evaluate([
                [
                    ['page@.page' => $page->route()],
                    ['page@.page' => $page->parent()->route()],
                ]
            ]);

            $pathsPerms = $this->getPathsPermsForUser(
                $this->getBranchUp($page)
            );
        }

        $this->grav['twig']->twig_vars['perms'] = $pathsPerms;
    }

    /**
     * [onAdminCreatePageFrontmatter]
     * Check that a user is allowed to create a page where it wants to.
     *
     * @param Event $event
     */
    public function canCreate( Event $event )
    {
        $page   = $event;
        $parent = $this->grav['page']->find($page['data']['route']);

        $header   = $page['header'];
        $redirect = $this->grav['request']->getServerParams()['HTTP_REFERER'];
        $user     = $this->grav['user'];

        // * Don’t complain if the user has the rights to create a page in the
        //   parent directory.
        // * The author of the parent page does not automatically have the
        //   rights to create children pages.
        if ($parent instanceof Page) {
            $perms = $this->getPermsForUser($parent, $user);

            if ($perms['create'] === true) {
                return;
            }
        }

        $this->grav['messages']->add(
                $this->grav['language']->translate(
                    'PLUGIN_ADMIN_PAGES_PERMISSIONS.WARNING_CREATE_IN_PARENT'
                ),
            'error'
        );

        $event->stopPropagation();

        $this->grav->redirect($redirect);

        return;
    }

    /**
     * [onAdminSave]
     * Check that a user is allowed to update a page.
     * Also prevent to update fields they are not authorized to.
     *
     * @param Event $event
     */
    public function canUpdate( Event $event )
    {
        $page = $event['object'];

        // Stop if we’re not dealing with a Page.
        if (!$page instanceof Page) {
            return;
        }

        $user         = $this->grav['user'];
        $pageOriginal = $page->getOriginal();
        $pageNew      = $page;

        // Don’t do anything if the user has the rights to update.
        if ($this->getPermsForUser($pageOriginal, $user)['update'] === true) {
            return;
        }

        $redirect       = $this->grav['request']->getServerParams()['REDIRECT_URL'];
        $perms          = $this->getPermsForUser($page->getOriginal(), $user);
        $this->warnings = 0;

        // Prevent users to update some properties.
        $page = $this->checkLockedProps(
            $pageOriginal,
            $pageNew
        );

        $page->header(
            $this->checkLockedHeaderProps(
                $pageOriginal->header(),
                $page->header()
            )
        );

        $event['object'] = $page;

        if ($this->warnings > 0) {
            $this->grav['messages']->add(
                $this->grav['language']->translate(
                    'PLUGIN_ADMIN_PAGES_PERMISSIONS.WARNING_PROPERTY_REVERTED'
                ),
                'notice'
            );
        }

        // Don’t do anything more if the user has the rights to update.
        if ($perms['update'] === true) {
            return;
        }

        $this->grav['messages']->add(
            $this->grav['language']->translate(
                'PLUGIN_ADMIN_PAGES_PERMISSIONS.WARNING_UPDATE'
            ),
            'error'
        );

        $event->stopPropagation();

        $this->grav->redirect($redirect);
    }
}
