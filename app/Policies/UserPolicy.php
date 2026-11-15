<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->role === 'admin';
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, User $manager): bool
    {
        return $this->viewAny($actor) && $manager->role === 'gestor';
    }

    public function view(User $actor, User $manager): bool
    {
        return $this->update($actor, $manager);
    }
}
