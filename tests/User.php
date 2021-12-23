<?php

namespace Junges\ACL\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Junges\ACL\Concerns\ACLWildcardsTrait;
use Junges\ACL\Concerns\HasGroups;
use Junges\ACL\Concerns\HasPermissions;

class User extends Authenticatable
{
    use HasGroups;
    use ACLWildcardsTrait;
    use Notifiable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'email'];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'users';
}
