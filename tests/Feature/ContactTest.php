<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_contacts(): void
    {
        $this->getJson('/api/contacts')->assertUnauthorized();
        $this->postJson('/api/contacts', $this->payload())->assertUnauthorized();
    }

    public function test_unverified_accounts_cannot_access_contacts(): void
    {
        $this->actingAs(User::factory()->unverified()->create(), 'web')
            ->getJson('/api/contacts')->assertForbidden();
    }

    public function test_creates_a_contact_owned_by_the_authenticated_account(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user, 'web')->postJson('/api/contacts', [
            ...$this->payload(), 'user_id' => $other->id,
            'email' => 'info@example.com', 'country' => 'it',
        ])->assertCreated()->assertJsonPath('data.country', 'IT')->assertJsonMissingPath('data.user_id');

        $this->assertDatabaseHas('contacts', ['user_id' => $user->id, 'name' => 'Studio Rossi', 'email' => 'info@example.com']);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_lists_only_owned_contacts_and_includes_dual_roles_in_customer_filter(): void
    {
        $user = User::factory()->create();
        $customer = Contact::factory()->for($user)->create(['name' => 'Alfa']);
        $both = Contact::factory()->for($user)->create(['name' => 'Beta', 'type' => 'both']);
        Contact::factory()->for($user)->create(['type' => 'supplier']);
        Contact::factory()->create(['type' => 'both']);

        $this->actingAs($user, 'web')->getJson('/api/contacts?type=customer')
            ->assertOk()->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.id', $customer->id)->assertJsonPath('data.1.id', $both->id);
    }

    public function test_search_is_case_insensitive_and_keeps_account_scope(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->for($user)->create(['email' => 'rossi@example.com']);
        Contact::factory()->create(['email' => 'rossi@example.com']);
        Contact::factory()->for($user)->create(['email' => 'other@example.com']);

        $this->actingAs($user, 'web')->getJson('/api/contacts?search=ROSSI')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $contact->id);
    }

    public function test_search_treats_sql_wildcards_as_literal_text(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->for($user)->create(['name' => '100% Studio']);
        Contact::factory()->for($user)->create(['name' => 'Altro studio']);

        $this->actingAs($user, 'web')->getJson('/api/contacts?search=100%25')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $contact->id);
    }

    public function test_paginates_contacts_in_stable_order(): void
    {
        $user = User::factory()->create();
        Contact::factory()->for($user)->count(21)->create(['name' => 'Studio']);

        $this->actingAs($user, 'web')->getJson('/api/contacts?page=2')
            ->assertOk()->assertJsonPath('total', 21)->assertJsonPath('last_page', 2)->assertJsonCount(1, 'data');
    }

    public function test_updates_a_contact_and_clears_optional_fields(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->for($user)->create(['email' => 'old@example.com']);

        $this->actingAs($user, 'web')->putJson('/api/contacts/'.$contact->id, [
            ...$this->payload(), 'name' => 'Nuovo nome', 'type' => 'both', 'email' => '',
        ])->assertOk()->assertJsonPath('data.email', null)->assertJsonPath('data.type', 'both');

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'name' => 'Nuovo nome', 'email' => null]);
    }

    public function test_deletes_an_owned_contact(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->for($user)->create();

        $this->actingAs($user, 'web')->deleteJson('/api/contacts/'.$contact->id)->assertNoContent();

        $this->assertModelMissing($contact);
    }

    public function test_other_accounts_cannot_read_update_or_delete_a_contact(): void
    {
        $contact = Contact::factory()->create(['name' => 'Privato']);
        $this->actingAs(User::factory()->create(), 'web');

        $this->getJson('/api/contacts/'.$contact->id)->assertNotFound();
        $this->putJson('/api/contacts/'.$contact->id, $this->payload())->assertNotFound();
        $this->deleteJson('/api/contacts/'.$contact->id)->assertNotFound();

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'name' => 'Privato']);
    }

    #[DataProvider('invalidData')]
    public function test_invalid_data_returns_422_without_creating_a_contact(string $field, mixed $value): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->postJson('/api/contacts', [...$this->payload(), $field => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('contacts', 0);
    }

    public static function invalidData(): array
    {
        return [
            'blank name' => ['name', '   '],
            'long name' => ['name', str_repeat('x', 256)],
            'invalid role' => ['type', 'admin'],
            'invalid entity' => ['entity_type', 'unknown'],
            'invalid email' => ['email', 'invalid'],
            'invalid pec' => ['pec', 'invalid'],
            'invalid country' => ['country', 'Italia'],
            'invalid sdi' => ['sdi_code', '123'],
            'long notes' => ['notes', str_repeat('x', 5001)],
        ];
    }

    private function payload(): array
    {
        return ['name' => 'Studio Rossi', 'type' => 'customer', 'entity_type' => 'company', 'country' => 'IT'];
    }
}
