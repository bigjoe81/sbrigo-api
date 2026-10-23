<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ContactPolicy
{
    public function view(User $user, Contact $contact): Response
    {
        return $user->id === $contact->user_id ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Contact $contact): Response
    {
        return $this->view($user, $contact);
    }

    public function delete(User $user, Contact $contact): Response
    {
        return $this->view($user, $contact);
    }
}
