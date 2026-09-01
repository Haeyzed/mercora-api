<?php

use App\Models\Landlord\Activity;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('returns a paginated list of activities', function () {
        Activity::factory()->count(2)->create();

        $this->getJson('/api/landlord/activities')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'log_name', 'description', 'event', 'subject_type', 'subject_id', 'causer_type', 'causer_id'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    });

    it('lists activities written through the activity helper', function () {
        [$user, $tenant] = activity()->withoutLogging(fn (): array => [
            User::factory()->create(),
            Tenant::factory()->create(),
        ]);

        activity()
            ->causedBy($user)
            ->performedOn($tenant)
            ->event('created')
            ->log('Tenant was created');

        $this->getJson('/api/landlord/activities')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', 'Tenant was created')
            ->assertJsonPath('data.0.event', 'created')
            ->assertJsonPath('data.0.causer_id', $user->id)
            ->assertJsonPath('data.0.subject_id', $tenant->id);
    });

    it('paginates activities using the per_page query parameter', function () {
        Activity::factory()->count(3)->create();

        $this->getJson('/api/landlord/activities?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');
    });

    it('filters activities by event', function () {
        Activity::factory()->create(['event' => 'deleted', 'description' => 'Tenant was deleted']);
        Activity::factory()->create();

        $this->getJson('/api/landlord/activities?filter[event]=deleted')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.event', 'deleted');
    });

    it('filters activities by log name', function () {
        Activity::factory()->create(['log_name' => 'billing']);
        Activity::factory()->create();

        $this->getJson('/api/landlord/activities?filter[log_name]=billing')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.log_name', 'billing');
    });

    it('filters activities by causer id', function () {
        $user = User::factory()->create();
        Activity::factory()->causedBy($user)->create();
        Activity::factory()->create();

        $this->getJson('/api/landlord/activities?filter[causer_id]='.$user->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.causer_id', $user->id);
    });

    it('filters activities by subject type', function () {
        Activity::factory()->create(['subject_type' => 'tenant', 'subject_id' => 'abc']);
        Activity::factory()->create();

        $this->getJson('/api/landlord/activities?filter[subject_type]=tenant')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.subject_type', 'tenant');
    });

    it('filters activities by subject id', function () {
        $tenant = activity()->withoutLogging(fn (): Tenant => Tenant::factory()->create());
        Activity::factory()->forSubject($tenant)->create();
        Activity::factory()->create();

        $this->getJson('/api/landlord/activities?filter[subject_id]='.$tenant->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.subject_id', $tenant->id);
    });

    it('searches activities by description', function () {
        Activity::factory()->create(['description' => 'Invoice was paid']);
        Activity::factory()->create(['description' => 'Tenant was created']);

        $this->getJson('/api/landlord/activities?search=Invoice')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', 'Invoice was paid');
    });

    it('returns all activities when search is blank', function () {
        Activity::factory()->count(2)->create();

        $this->getJson('/api/landlord/activities?search=')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    });

    it('ignores unknown filters instead of querying arbitrary columns', function () {
        Activity::factory()->create();

        $this->getJson('/api/landlord/activities?filter[password]=secret')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    });

    it('includes the causer and subject when requested', function () {
        [$user, $tenant] = activity()->withoutLogging(fn (): array => [
            User::factory()->create(['name' => 'Ada Lovelace']),
            Tenant::factory()->create(['name' => 'Acme Stores']),
        ]);
        Activity::factory()->causedBy($user)->forSubject($tenant)->create();

        $this->getJson('/api/landlord/activities?include=causer,subject')
            ->assertOk()
            ->assertJsonPath('data.0.causer.name', 'Ada Lovelace')
            ->assertJsonPath('data.0.subject.name', 'Acme Stores');
    });
});

describe('show', function () {
    it('returns a single activity', function () {
        $activity = Activity::factory()->create(['description' => 'Tenant was created']);

        $this->getJson("/api/landlord/activities/{$activity->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $activity->id)
            ->assertJsonPath('data.description', 'Tenant was created')
            ->assertJsonMissingPath('data.causer')
            ->assertJsonMissingPath('data.subject');
    });

    it('returns 404 when the activity does not exist', function () {
        $this->getJson('/api/landlord/activities/999')
            ->assertNotFound();
    });
});

describe('store', function () {
    it('returns 405 when creating an activity through the API', function () {
        $this->postJson('/api/landlord/activities', [
            'description' => 'Invented event',
        ])->assertMethodNotAllowed();

        $this->assertDatabaseCount('activity_log', 0);
    });
});

describe('update', function () {
    it('returns 405 when updating an activity through the API', function () {
        $activity = Activity::factory()->create(['description' => 'Tenant was created']);

        $this->putJson("/api/landlord/activities/{$activity->id}", [
            'description' => 'Rewritten event',
        ])->assertMethodNotAllowed();

        $this->assertDatabaseHas('activity_log', [
            'id' => $activity->id,
            'description' => 'Tenant was created',
        ]);
    });
});

describe('destroy', function () {
    it('permanently deletes an activity', function () {
        $activity = Activity::factory()->create();

        $this->deleteJson("/api/landlord/activities/{$activity->id}")
            ->assertNoContent();

        $this->assertModelMissing($activity);
    });

    it('returns 404 when the activity does not exist', function () {
        $this->deleteJson('/api/landlord/activities/999')
            ->assertNotFound();
    });
});

describe('destroyMany', function () {
    it('permanently deletes the given activities', function () {
        $first = Activity::factory()->create();
        $second = Activity::factory()->create();

        $this->deleteJson('/api/landlord/activities/destroy-many', [
            'ids' => [$first->id, $second->id],
        ])->assertNoContent();

        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
    });

    it('returns 422 when ids are missing', function () {
        $this->deleteJson('/api/landlord/activities/destroy-many', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);
    });

    it('returns 422 when an id does not exist', function () {
        $this->deleteJson('/api/landlord/activities/destroy-many', [
            'ids' => [999],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    });
});
