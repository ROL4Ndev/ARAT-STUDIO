<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CatalogPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->is_active && $u->role === 'admin';
    }

    public function create(User $u): bool
    {
        return $this->viewAny($u);
    }

    public function view(User $u, Model $record): bool
    {
        return $this->viewAny($u);
    }

    public function update(User $u, Model $record): bool
    {
        return $this->viewAny($u);
    }

    public function changeStatus(User $u, Model $record): bool
    {
        return $this->viewAny($u);
    }
}
