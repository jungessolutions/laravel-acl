<?php

namespace Junges\ACL\Concerns;

trait ACLWildcardsTrait
{
    /**
     * Check if the user has a permission, but only if this permission is directly associated to the user.
     *
     * @param  string  $permissionSlug
     * @return bool
     */
    public function hasPermissionWithWildcards(string $permissionSlug): bool
    {
        $permissionSlug = str_replace('*', '%', $permissionSlug);

        return (bool) $this->permissions()
            ->where('slug', 'like', $permissionSlug)
            ->count();
    }
}
