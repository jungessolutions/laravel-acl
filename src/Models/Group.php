<?php

namespace Junges\ACL\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Junges\ACL\AclRegistrar;
use Junges\ACL\Concerns\HasPermissions;
use Junges\ACL\Concerns\RefreshesPermissionCache;
use Junges\ACL\Contracts\Group as GroupContract;
use Junges\ACL\Exceptions\GroupAlreadyExistsException;
use Junges\ACL\Exceptions\GroupDoesNotExistException;
use Junges\ACL\Exceptions\GuardDoesNotMatch;
use Junges\ACL\Guard;

class Group extends Model implements GroupContract
{
    use HasPermissions;
    use RefreshesPermissionCache;

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->guarded[] = $this->primaryKey;
    }

    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? Guard::getDefaultName(static::class);

        $params = ['name' => $attributes['name'], 'guard_name' => $attributes['guard_name']];

        if (AclRegistrar::$teams) {
            if (array_key_exists(AclRegistrar::$teamsKey, $attributes)) {
                $params[AclRegistrar::$teamsKey] = $attributes[AclRegistrar::$teamsKey];
            } else {
                $attributes[AclRegistrar::$teamsKey] = app(AclRegistrar::class)->getPermissionsTeamId();
            }
        }

        if (static::findByParam($params)) {
            throw GroupAlreadyExistsException::create($attributes['name'], $attributes['guard_name']);
        }

        return static::query()->create($attributes);
    }

    public function getTable()
    {
        return config('acl.tables.groups', parent::getTable());
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            config('acl.models.permission'),
            config('acl.tables.group_has_permissions'),
            AclRegistrar::$pivotGroup,
            AclRegistrar::$pivotPermission
        );
    }

    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            getModelForGuard($this->attributes['guard_name']),
            'model',
            config('acl.tables.model_has_groups'),
            AclRegistrar::$pivotGroup,
            config('acl.column_names.model_morph_key')
        );
    }

    /**
     * {@inheritDoc}
     */
    public static function findByName(string $name, $guardName = null): GroupContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $group = static::findByParam(['name' => $name, 'guard_name' => $guardName]);

        if (! $group) {
            throw GroupDoesNotExistException::named($name);
        }

        return $group;
    }

    /**
     * {@inheritDoc}
     */
    public static function findById(int $id, $guardName = null): GroupContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $group = static::findByParam(['id' => $id, 'guard_name' => $guardName]);

        if (! $group) {
            throw GroupDoesNotExistException::withId($id);
        }

        return $group;
    }

    /**
     * {@inheritDoc}
     */
    public static function findOrCreate(string $name, $guardName = null): GroupContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $group = static::findByParam(['name' => $name, 'guard_name' => $guardName]);

        if (! $group) {
            return static::query()->create(['name' => $name, 'guard_name' => $guardName] + (AclRegistrar::$teams ? [AclRegistrar::$teamsKey => app(AclRegistrar::class)->getPermissionsTeamId()]: []));
        }

        return $group;
    }

    public function getRouteKeyName(): string
    {
        return config('acl.route_model_binding_keys.group_model', 'slug');
    }

    protected static function findByParam(array $params = []): ?GroupContract
    {
        $query = static::query();

        $query->when(AclRegistrar::$teams, fn ($q) => $q->where(function ($q) use ($params) {
            $q->whereNull(AclRegistrar::$teamsKey)
                ->orWhere(AclRegistrar::$teamsKey, $params[AclRegistrar::$teamsKey] ?? app(AclRegistrar::class)->getPermissionsTeamId());
        }));

        unset($params[AclRegistrar::$teamsKey]);

        foreach ($params as $key => $value) {
            $query->where($key, $value);
        }

        return $query->first();
    }

    /**
     * {@inheritDoc}
     */
    public function hasPermission($permission): bool
    {
        $permissionClass = $this->getPermissionClass();

        if (is_string($permission)) {
            $permission = $permissionClass->findByName($permission, $this->getDefaultGuardName());
        }

        if (is_int($permission)) {
            $permission = $permissionClass->findById($permission, $this->getDefaultGuardName());
        }

        if (! $this->getGuardNames()->contains($permission->guard_name)) {
            throw GuardDoesNotMatch::create($permission->guard_name, $this->getGuardNames());
        }

        return $this->permissions->contains('id', $permission->id);
    }
}
